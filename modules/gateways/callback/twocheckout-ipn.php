<?php

use WHMCS\Database\Capsule;
use WHMCS\Billing\Invoice;
use WHMCS\Carbon;

require_once realpath(dirname(__FILE__)) . "/../twocheckout/lib/TwocheckoutApi.php";
require_once __DIR__ . '/../../../init.php';

if (!isset($_GET['tco_type']) || !in_array($_GET['tco_type'], ['inline', 'convertplus', 'api'])) {
    die("2Checkout gateway is not available!");
}
App::load_function('gateway');
App::load_function('invoice');
$gatewayType = $_GET['tco_type'];
$gatewayModuleName = 'twocheckout' . $gatewayType;
$twocheckoutConfig = getGatewayVariables($gatewayModuleName);

//race condition issue
sleep(rand(1, 5));

if (!$twocheckoutConfig['type']) {
    die("Module Not Activated");
}
$skipFraud = isset($twocheckoutConfig['skipFraud']) && $twocheckoutConfig['skipFraud'] == 'on';

if ($gatewayType === 'api') {
    $refNo = isset($_GET['REFNO']) && !empty($_GET['REFNO']) ? $_GET['REFNO'] : null;
} else {
    $refNo = isset($_GET['refno']) && !empty($_GET['refno']) ? $_GET['refno'] : null;
}

if ($refNo) {
    $return = "<html>\n<head>\n<title>" . $twocheckoutConfig['companyname'] . "</title>\n";
    $orderData = TwocheckoutApi::callAPI("GET", "orders/" . $refNo . "/", $twocheckoutConfig);

    if (isset($orderData['RefNo']) && isset($orderData['ExternalReference'])) {
        $transactionId = $orderData['RefNo'];
        $invoiceId = $orderData['ExternalReference'];
        $invoiceId = checkCbInvoiceID($invoiceId, $twocheckoutConfig['paymentmethod']);
        if (in_array($orderData['Status'], ['AUTHRECEIVED', 'COMPLETE'])) {

            // add subscription reference for all recurring products (for possible cancel action)
            foreach ($orderData['Items'] as $item) {
                if (isset($item['ProductDetails']['Subscriptions']) && count($item['ProductDetails']['Subscriptions'])) {
                    $subscriptionReference = $item['ProductDetails']['Subscriptions'][0]['SubscriptionReference'];
                    $id = preg_replace('/\D/', '', $item['ExternalReference']);
                    Capsule::table('tblhosting')->whereId($id)->update(['subscriptionid' => $subscriptionReference]);
                }
            }

            $baseUrl = \App::getSystemURL() . "viewinvoice.php?id=" . $invoiceId;
            if ($skipFraud) {
                checkCbTransID($transactionId);
                logTransaction($twocheckoutConfig['paymentmethod'], $orderData, 'Success');
                addInvoicePayment($invoiceId, $transactionId, null, null, $twocheckoutConfig['paymentmethod']);
                if ($gatewayType === 'api') {
                    callback3DSecureRedirect($invoiceId, true);
                } else {
                    $return .= "<meta http-equiv=\"refresh\" content=\"0;url=" . $baseUrl . "&paymentsuccess=true\">";
                }
            } else {
                logTransaction($twocheckoutConfig['paymentmethod'], $orderData, 'Waiting for fraud review.');
                $return .= "<meta http-equiv=\"refresh\" content=\"0;url=" . $baseUrl . "&pendingreview=true\">";
            }
        } else {
            logTransaction($twocheckoutConfig['paymentmethod'], $orderData, 'Unsuccessful');
            if ($gatewayType === 'api') {
                callback3DSecureRedirect($invoiceId, false);
            } else {
                $baseUrl = \App::getSystemURL() . "/clientarea.php?action=invoices";
                $return .= "<meta http-equiv=\"refresh\" content=\"0;url=" . $baseUrl . "\">";
            }
        }
    } elseif (isset($orderData['error_code'])) {
        logTransaction($twocheckoutConfig['paymentmethod'], $_GET, "Request error");
        redirSystemURL("action=invoices", "clientarea.php");
    }
    $return .= "</head>\n<body>\n";
    $return .= "\n</body>\n</html>\n";
    echo $return;

} elseif (isset($_POST['REFNO']) && !empty($_POST['REFNO'])) {
    $signature = $_POST["HASH"];
    $result = "";

    // Verify Hash
    if (isIpnResponseValid($_POST, $twocheckoutConfig['secretKey'])) {
        echo calculateIpnResponse($_POST, $twocheckoutConfig['secretKey']);
        flush();
        ob_flush();

        $paymentAmount = 0;
        $exchange_rate = (float)$_POST['FX_RATE'] > 0 ? (float)$_POST['FX_RATE'] : 1;
        $fx_commission = ((float)$_POST['FX_MARKUP'] > 0) ? 100 / (100 - (float)$_POST['FX_MARKUP']) : 1;
        if (!empty($_POST['IPN_PRICE'])) {
            foreach ($_POST['IPN_PRICE'] as $priceAdd) {
                $paymentAmount = $paymentAmount + (((float)$priceAdd) * $exchange_rate * $fx_commission);
            }
        }
        $paymentAmount = number_format($paymentAmount, 2);

        $fee = (!empty($_POST['PAYABLE_AMOUNT'])) ? $paymentAmount - (float)$_POST['PAYABLE_AMOUNT'] : 0;
        $fee = number_format($fee, 2);

        // IPN for new recurring invoice
        if (isset($_POST["ORIGINAL_REFNOEXT"][0]) && !empty($_POST["ORIGINAL_REFNOEXT"][0]) && ($_POST["FRAUD_STATUS"] == 'APPROVED')) {
            $transactionId = $_POST["REFNO"];
            $externalRef = $_POST["ORIGINAL_REFNOEXT"][0];
            $serviceId = $_POST["IPN_EXTERNAL_REFERENCE"][0];
            $serviceId = preg_replace('/\D/', '', $serviceId);
            if (empty($serviceId)) {
                $serviceId = $externalRef;
            }

            if (!empty($externalRef) && !empty($serviceId)) {
                checkCbTransID($transactionId);
                logTransaction($twocheckoutConfig['paymentmethod'], $_POST, "2Checkout/Verifone order update!");

                $newInvoiceItem = (array)Capsule::table('tblinvoiceitems')
                    ->join('tblinvoices', 'tblinvoiceitems.invoiceid', '=', 'tblinvoices.id')
                    ->where('tblinvoiceitems.relid', $serviceId)
                    ->where('tblinvoices.status', 'Unpaid')
                    ->first();
                $invoiceId = $newInvoiceItem['invoiceid'];
                $orderData = TwocheckoutApi::callAPI("GET", "orders/" . $transactionId . "/", $twocheckoutConfig);

                if (in_array($orderData['Status'], ['AUTHRECEIVED', 'COMPLETE'])) {
                    if (!empty($invoiceId)) {
                        addInvoicePayment($invoiceId, $transactionId, $paymentAmount, $fee, $twocheckoutConfig['paymentmethod']);
                    } else { // try to get a paid or cancelled invoice and apply an overpayment to client's credit balance
                        $paidInvoiceItem = (array)Capsule::table('tblinvoiceitems')
                            ->join('tblinvoices', 'tblinvoiceitems.invoiceid', '=', 'tblinvoices.id')
                            ->where('tblinvoiceitems.relid', $serviceId)
                            ->orderBy("tblinvoiceitems.id", "desc")
                            ->first();
                        $userID = $paidInvoiceItem['userid'];
                        $invoiceId = $paidInvoiceItem['invoiceid'];
                        if (!empty($userID)) {
                            // add credit balance
                            try {
                                (array)Capsule::table('tblaccounts')->insert([
                                    [
                                        "userid" => intval($userID),
                                        "currency" => 0,
                                        "gateway" => $twocheckoutConfig['name'],
                                        "date" => Carbon::now(),
                                        "description" => "Invoice " . $invoiceId . " overpayment to credit balance",
                                        "amountin" => $paymentAmount,
                                        "fees" => $fee,
                                        "rate" => 1,
                                        "transid" => $transactionId
                                    ]
                                ]);
                                (array)Capsule::table('tblcredit')->insert([
                                    "clientid" => intval($userID),
                                    "date" => Carbon::now(),
                                    "description" => "Invoice " . $invoiceId . " overpayment to credit balance",
                                    "amount" => $paymentAmount
                                ]);
                                //Overpayment to credit balance
                                (array)Capsule::table('tblclients')->where("id", intval($userID))->increment("credit", $paymentAmount);
                            } catch (\Exception $e) {
                                //failed to add credit;
                                logActivity("Failed to add invoice " . $invoiceId . " overpayment to credit: " . $e->getMessage(), $userID);
                            }
                        }
                    }
                }
            } else {
                logModuleCall($gatewayModuleName, 'error', '', 'Recurring 2Checkout transaction ' . $transactionId . ' IPN with no item external reference');
            }
            // IPN for any case other than recurring
        } else {
            if (isset($_POST["REFNOEXT"]) && !empty($_POST["REFNOEXT"]) && $_POST["FRAUD_STATUS"] == 'APPROVED') {

                if (!$skipFraud) {
                    $transactionId = $_POST["REFNO"];
                    checkCbTransID($transactionId);
                    $invoiceId = checkCbInvoiceID($_POST["REFNOEXT"], $twocheckoutConfig['name']);
                    addInvoicePayment($invoiceId, $transactionId, $paymentAmount, $fee, $twocheckoutConfig['paymentmethod']);
                }
                logTransaction($twocheckoutConfig['name'], $_POST, 'Success');
            } elseif (isset($_POST["REFNOEXT"]) && !empty($_POST["REFNOEXT"]) && ($_POST["FRAUD_STATUS"] == 'DENIED')) {
                logTransaction($twocheckoutConfig['paymentmethod'], $_POST, 'Transaction DENIED');
            }
        }
    } else {
        logModuleCall($gatewayModuleName, 'error', '', 'Error. Cannot verify signature.');
        echo '<EPAYMENT>Error. Cannot verify signature.</EPAYMENT>';
    }
}

function ArrayExpand($array)
{
    $retval = "";
    for ($i = 0; $i < sizeof($array); $i++) {
        $converted_str = htmlspecialchars_decode(StripSlashes($array[$i]), ENT_COMPAT | ENT_QUOTES);
        $size = strlen(StripSlashes($converted_str));
        $retval .= $size . $converted_str;
    }

    return $retval;
}

function hmac($key, $data)
{
    $b = 64;
    if (strlen($key) > $b) {
        $key = pack("H*", md5($key));
    }
    $key = str_pad($key, $b, chr(0x00));
    $ipad = str_pad('', $b, chr(0x36));
    $opad = str_pad('', $b, chr(0x5c));
    $k_ipad = $key ^ $ipad;
    $k_opad = $key ^ $opad;

    return md5($k_opad . pack("H*", md5($k_ipad . $data)));
}

/**
 * @return bool
 */
function isIpnResponseValid($params, $secret_key)
{
    $result = '';
    $receivedHash = $params['HASH'];
    foreach ($params as $key => $val) {

        if ($key != "HASH") {
            if (is_array($val)) {
                $result .= ArrayExpand($val);
            } else {
                $converted_str = htmlspecialchars_decode(StripSlashes($val), ENT_COMPAT | ENT_QUOTES);
                $size = strlen(StripSlashes($converted_str));
                $result .= $size . $converted_str;
            }
        }
    }
    if (isset($params['REFNO']) && !empty($params['REFNO'])) {
        $calcHash = hmac($secret_key, $result);
        if ($receivedHash === $calcHash) {
            return true;
        }
    }

    return false;
}

/**
 * @param $params
 * @param $secret_key
 *
 * @return string
 */
function calculateIpnResponse($params, $secret_key)
{
    $resultResponse = '';
    $ipnParamsResponse = [];
    $ipnParamsResponse['IPN_PID'][0] = $params['IPN_PID'][0];
    $ipnParamsResponse['IPN_PNAME'][0] = $params['IPN_PNAME'][0];
    $ipnParamsResponse['IPN_DATE'] = $params['IPN_DATE'];
    $ipnParamsResponse['DATE'] = date('YmdHis');

    foreach ($ipnParamsResponse as $key => $val) {
        $resultResponse .= ArrayExpand((array)$val);
    }

    return sprintf('<EPAYMENT>%s|%s</EPAYMENT>', $ipnParamsResponse['DATE'], hmac($secret_key, $resultResponse));
}
