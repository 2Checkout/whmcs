<?php

require_once realpath(dirname(__FILE__)) . "/twocheckoutapi/lib/TwocheckoutApi.php";
use WHMCS\Billing\Invoice;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function twocheckoutapi_MetaData()
{
    return array(
        'DisplayName' => '2Checkout API Gateway',
        'APIVersion' => '1.1'
    );
}

function twocheckoutapi_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => '2Checkout API Gateway',
        ),
        'accountId' => array(
            'FriendlyName' => 'Merchant Code',
            'Type' => 'text',
            'Size' => '30',
            'Default' => '',
            'Description' => 'Enter your Merchant Code here',
        ),
        'secretWord' => array(
            'FriendlyName' => 'Secret Word',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter secret word here',
        ),
        'secretKey' => array(
            'FriendlyName' => 'Secret Key',
            'Type' => 'password',
            'Size' => '50',
            'Default' => '',
            'Description' => 'Enter secret key here',
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Tick to enable test mode',
        ),
        'skipFraud' => array(
            'FriendlyName' => 'Skip 2CO Fraud Check',
            'Type' => 'yesno',
            'Description' => 'Tick to mark invoices as paid without waiting for 2Checkout fraud review.',
        ),
        'refundReasons' => array(
            'FriendlyName' => 'Reasons for Refunds',
            'Type'         => 'textarea',
            'Rows'         => '10',
            'Cols'         => '30',
            'Description'  => 'Enter the reasons for refunds that you have <a target="_blank" href="https://knowledgecenter.2checkout.com/Documentation/27Refunds_and_chargebacks/Refunds#Adding_custom_refund_reasons">setup in your 2Checkout account</a>.',
        )
    );
}

add_hook("ClientAreaFooterOutput", 1, function (array $vars) {
    $filename = $vars["filename"];
    $return = "";
    $requiredFiles = array("cart", "index", "account-paymentmethods-manage");
    if (in_array($filename, $requiredFiles)) {
        $return = "<script type=\"text/javascript\" src=\"https://2pay-js.2checkout.com/v1/2pay.js\"></script>";
    }
    return $return;
});

function twocheckoutapi_cc_validation(array $params = array())
{
    if (App::isInRequest("remoteStorageToken")) {
        WHMCS\Session::set("remoteStorageToken", (string) App::getFromRequest("remoteStorageToken"));
    }
    return "";
}
function twocheckoutapi_credit_card_input(array $params = array())
{
    $assetHelper = DI::make("asset");
    $now = time();
    $jsUrl = $assetHelper->getWebRoot() . "/modules/gateways/twocheckoutapi/twocheckoutapi.js?a=" . $now;

    return "<script type=\"text/javascript\">\n    var accountId = '" . $params["accountId"] . "';" . "\n</script>\n<script type=\"text/javascript\" src=\"" . $jsUrl . "\"></script>";
}

function twocheckoutapi_storeremote(array $params = array())
{
    $token = WHMCS\Session::getAndDelete("remoteStorageToken");
    if (!$token && App::isInRequest("remoteStorageToken")) {
        $token = (string) App::getFromRequest("remoteStorageToken");
    }

    $response = [
        'success' => true,
        'token' => $token,
    ];

    return array("gatewayid" => $token, "status" => "success", "rawdata" => json_encode($response));
}

function twocheckoutapi_capture($params)
{
    // Gateway Configuration Parameters
    $twocheckoutConfig = array(
        "accountId" => $params['accountId'],
        "secretKey" => $params['secretKey']
    );

    $itemsArray = array();

    $currency = $params['currency'];

    $invoice = Invoice::find($params['invoiceid']);
    $lineitemDetails = $invoice->getBillingValues();

    $overdue = $lineitemDetails['overdue'];
    unset($lineitemDetails['overdue']);

    $recurring = false;

    foreach ($lineitemDetails as $lineitemDetail) {
        $lineItem = array();
        if (array_key_exists('firstPaymentAmount', $lineitemDetail)) {
            $lineItemAmount = $lineitemDetail['firstPaymentAmount'];
        } else {
            $lineItemAmount = $lineitemDetail['lineItemAmount'];
        }

        if ($lineItemAmount >= 0) {
            $lineItem["Code"] = null;
            $lineItem["Quantity"] = "1";
            $lineItem["IsDynamic"] = true;
            $lineItem["Tangible"] = false;
            $lineItem["PurchaseType"] = "PRODUCT";
            $lineItem["Name"] = $lineitemDetail['description'];
            $lineItem["Price"] = array(
                "Amount" => abs($lineItemAmount),
                "Type" => "CUSTOM",
                "Currency" => $currency
            );
            $lineItem["ExternalReference"] = $lineitemDetail['itemId'];

            if (!$overdue && $lineitemDetail['recurringCyclePeriod'] && $lineitemDetail['recurringCyclePeriod'] > 0) {
                $recurringDetails = array();
                $recurringDetails["CycleLength"] = $lineitemDetail['recurringCyclePeriod'];
                $recurringDetails["CycleUnit"] = mapRecurringUnit($lineitemDetail['recurringCycleUnits']);
                $recurringDetails["CycleAmount"] = abs($lineItemAmount);
                $recurringDetails["ContractLength"] = 0;
                $recurringDetails["ContractUnit"] = "FOREVER";
                $lineItem['RecurringOptions'] = $recurringDetails;

                $recurring = true;
            }
        } else {
            // We have a discount and need to apply it as a coupon
            $lineItem["Code"] = null;
            $lineItem["Quantity"] = "1";
            $lineItem["IsDynamic"] = true;
            $lineItem["Tangible"] = false;
            $lineItem["PurchaseType"] = "COUPON";
            $lineItem["Name"] = $lineitemDetail['description'];
            $lineItem["Price"] = array(
                "Amount" => abs($lineitemDetail['amount']),
                "Type" => "CUSTOM",
                "Currency" => $currency
            );
        }
        array_push($itemsArray, $lineItem);
    }

    // Add credit as coupon if present
    if ($invoice->credit > 0) {
        $lineItemCoupon = array(
            "Code" => null,
            "Quantity" => "1",
            "IsDynamic" => true,
            "Tangible" => false,
            "PurchaseType" => "COUPON",
            "Name" => "Credit",
            "Price" => array(
                "Amount" => ($invoice->credit),
                "Type" => "CUSTOM",
                "Currency" => $currency
            )
        );
        array_push($itemsArray, $lineItemCoupon);
    }

    // Billing Details
    $billingDetails = array();
    $billingDetails['FirstName'] = $params['clientdetails']['firstname'];
    $billingDetails['LastName'] = $params['clientdetails']['lastname'];
    $billingDetails['Email'] = $params['clientdetails']['email'];
    $billingDetails['Address1'] = $params['clientdetails']['address1'];
    if (isset($params['clientdetails']['address2']) and !empty($params['clientdetails']['address2'])) {
        $billingDetails['Address2'] = $params['clientdetails']['address2'];
    }
    $billingDetails['City'] = $params['clientdetails']['city'];
    if (isset($params['clientdetails']['state']) and !empty($params['clientdetails']['state'])) {
        $billingDetails['State'] = $params['clientdetails']['state'];
    }
    $billingDetails['Zip'] = $params['clientdetails']['postcode'];
    $billingDetails['CountryCode'] = $params['clientdetails']['country'];
    if (isset($params['clientdetails']['phonenumber']) and !empty($params['clientdetails']['phonenumber'])) {
        $billingDetails['Phone'] = $params['clientdetails']['phonenumber'];
    }
    if (isset($params['clientdetails']['companyname']) and !empty($params['clientdetails']['companyname'])) {
        $billingDetails['Company'] = $params['clientdetails']['companyname'];
    }

    // Payment Method Details
    $paymentMethodDetails = array(
        "EesToken" => $params['gatewayid'],
        "Vendor3DSReturnURL" => $params['systemurl'] . "modules/gateways/callback/twocheckoutapi.php",
        "Vendor3DSCancelURL" => $params['systemurl'] . "modules/gateways/callback/twocheckoutapi.php",
    );

    if ($recurring == true) {
        $paymentMethodDetails['RecurringEnabled'] = $recurring;
    }

    // Order Details
    $orderDetails = array(
        "Currency" => $currency,
        "Language" => $params['en'],
        "Country" => $params['clientdetails']['country'],
        "ExternalReference" => $params['invoiceid'],
        "Source" => 'whmcs-psp-api',
        "Items" => $itemsArray,
        "PaymentDetails" => array(
            "Type" => "EES_TOKEN_PAYMENT",
            "Currency" => $currency,
            "PaymentMethod" => $paymentMethodDetails
        ),
        "BillingDetails" => $billingDetails
    );

    if (isset($params['testMode']) and !empty($params['testMode'])) {
        $orderDetails['PaymentDetails']['Type'] = 'TEST';
    }

    // Use to not store payment methods, this is a work around until whmcs gets back to us on a proper way to handle this
     $payMethod = \WHMCS\Payment\PayMethod\Model::find($params['payMethod']['id']);
     $payment = $payMethod->payment;
     $payment->deleteRemote();
     $payMethod->delete();

    try {
        $responseData = TwocheckoutApi::callAPI("POST", "orders/", $twocheckoutConfig, $orderDetails);

        if (isset($responseData['Status'])) {
            if (isset($responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']) &&
                isset($responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']['Href']) &&
                !empty($responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']['Href'])) {
                header("Location: " . $responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']['Href'] . '?avng8apitoken=' . $responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']['Params']['avng8apitoken']);
                exit;
            } else if ($responseData['Status'] == 'AUTHRECEIVED') {
                // check if we want to mark the invoice as pending or paid
                if (isset($params['skipFraud']) and !empty($params['skipFraud'])) {

                    $returnData = [
                        'status'  => 'success',
                        'rawdata' => $responseData,
                        'transid' => $responseData['RefNo']
                    ];
                } else {
                    $url = \App::getSystemURL() . "viewinvoice.php?id=" . $params['invoiceid'] . "&pendingreview=true";
                    header("Location:" . $url);
                    exit;
                }

                $returnData = [
                    'status'  => 'success',
                    'rawdata' => $responseData,
                    'transid' => $responseData['RefNo']
                ];
            } else {
                $returnData = [
                    'status' => 'declined',
                    'declinereason' => 'Credit card declined. Please contact issuer.',
                    'rawdata' => $responseData,
                ];
            }
        } else {
            $returnData = [
                'status' => 'declined',
                'declinereason' => 'An error occured during processing. Please try again later.',
                'rawdata' => $responseData,
            ];
        }
    } catch (Exception $e) {
        $returnData = [
            'status' => 'declined',
            'declinereason' => 'An error occured during processing. Please try again later.',
            'rawdata' => $responseData,
        ];
    }
    return $returnData;
}

function twocheckoutapi_refund($params)
{
    // Gateway Configuration Parameters
    $twocheckoutConfig = array(
        "accountId" => $params['accountId'],
        "secretKey" => $params['secretKey']
    );

    // Refund Reason
    if (isset($_POST['reason']) && !empty($_POST['reason'])) {
        $refundReason = $_POST['reason'];
    } else {
        $refundReason = 'Cancellation';
    }

    // Refund Comment
    if (isset($_POST['comment']) && !empty($_POST['comment'])) {
        $refundComment = $_POST['comment'];
    } else {
        $refundComment = '';
    }

    $orderData = TwocheckoutApi::callAPI("GET", "orders/{$params['transid']}/", $twocheckoutConfig);

    if ($params['amount'] == $orderData["GrossPrice"]) {
        // Refund Details
        $refundDetails = [
            "amount"  => $params['amount'],
            "comment" => $refundComment,
            "reason"  => $refundReason
        ];
    } else {
        $lineItems = $orderData["Items"];
        usort($lineItems, "cmpPrices");
        $lineitemReference = $lineItems[0]["LineItemReference"];
        if ($lineItems[0]['Price']['GrossPrice'] >= $params['amount']) {
            // Refund Item Details
            $itemsArray[] = array(
                "Quantity"          => "1",
                "LineItemReference" => $lineitemReference,
                "Amount"            => $params['amount']
            );

            // Refund Details
            $refundDetails = [
                "amount"  => $params['amount'],
                "comment" => $refundComment,
                "reason"  => $refundReason,
                "items"   => $itemsArray
            ];
        } else {
            return [
                'status'  => 'error',
                'rawdata' => 'Partial refund amount cannot exceed the highest priced item. Please login to your 2Checkout admin to issue the partial refund manually.',
                'transid' => $params['transid'],
            ];
        }
    }

    try {
        $responseData = TwocheckoutApi::callAPI("POST", "orders/{$params['transid']}/refund/", $twocheckoutConfig, $refundDetails);
        if (!isset($responseData['error_code']) && $responseData == '1') {
            $returnData = [
                'status' => 'success',
                'transid' => $params['transid'],
                'rawdata' => $responseData,
            ];
        } else {
            $returnData = [
                'status' => 'declined',
                'transid' => $params['transid'],
                'rawdata' => $responseData,
            ];
        }
    } catch (Exception $e) {
        $returnData = [
            'status' => 'declined',
            'transid' => $params['transid']
        ];
    }

    return $returnData;
}

function cmpPrices($a, $b) {
    if ($a['Price']['GrossPrice'] < $b['Price']['GrossPrice']) {
        return 1;
    } else if ($a['Price']['GrossPrice'] > $b['Price']['GrossPrice']) {
        return -1;
    } else {
        return 0;
    }
}

function mapRecurringUnit($unit) {
    $recurringUnits = [
        "Days" => "DAY",
        "Weeks" => "WEEK",
        "Months" => "MONTH",
        "Years" => "YEAR"
    ];

    return $recurringUnits[$unit];
}
