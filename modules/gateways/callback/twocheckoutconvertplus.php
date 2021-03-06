<?php

use WHMCS\Database\Capsule;
use WHMCS\Billing\Invoice;

require_once realpath( dirname( __FILE__ ) ) . "/../twocheckoutconvertplus/lib/ConvertPlusHelper.php";
require_once realpath( dirname( __FILE__ ) ) . "/../twocheckoutconvertplus/lib/TwocheckoutApiConvertPlus.php";
require_once __DIR__ . '/../../../init.php';
App::load_function( 'gateway' );
App::load_function( 'invoice' );

$gatewayModuleName = basename( __FILE__, '.php' );
$twocheckoutConfig = getGatewayVariables( $gatewayModuleName );
$skipFraud         = ( isset( $twocheckoutConfig['skipFraud'] ) && $twocheckoutConfig['skipFraud'] == 'on' ) ? true : false;

if ( ! $twocheckoutConfig['type'] ) {
    die( "Module Not Activated" );
}

if ( isset( $_GET['refno'] ) && ! empty( $_GET['refno'] ) ) {

    $return    = "<html>\n<head>\n<title>" . $twocheckoutConfig['companyname'] . "</title>\n";
    $orderData = TwocheckoutApiConvertPlus::callAPI( "GET", "orders/" . $_GET['refno'] . "/", $twocheckoutConfig );
    if ( isset( $orderData['RefNo'] ) && isset( $orderData['ExternalReference'] ) ) {

        $transactionId = $orderData['RefNo'];
        $invoiceId     = $orderData['ExternalReference'];
        $invoiceId     = checkCbInvoiceID( $invoiceId, $twocheckoutConfig['paymentmethod'] );

        if ( in_array( $orderData['Status'], [ 'AUTHRECEIVED', 'COMPLETE' ] ) ) {
            $baseUrl = \App::getSystemURL() . "viewinvoice.php?id=" . $invoiceId;

            if ( $skipFraud ) {
                checkCbTransID( $transactionId );
                logTransaction( $twocheckoutConfig['paymentmethod'], $orderData, 'Success' );
                addInvoicePayment( $invoiceId, $transactionId, null, null, $twocheckoutConfig['paymentmethod'] );
                $return .= "<meta http-equiv=\"refresh\" content=\"0;url=" . $baseUrl . "&paymentsuccess=true\">";
            } else {
                logTransaction( $twocheckoutConfig['paymentmethod'], $orderData,
                    'Waiting for fraud review.' );
                $return .= "<meta http-equiv=\"refresh\" content=\"0;url=" . $baseUrl . "&pendingreview=true\">";
            }
        } else {
            $baseUrl = \App::getSystemURL() . "/clientarea.php?action=invoices";
            logTransaction( $twocheckoutConfig['paymentmethod'], $orderData, 'Unsuccessful' );
            $return .= "<meta http-equiv=\"refresh\" content=\"0;url=" . $baseUrl . "\">";
        }
    } elseif ( isset( $orderData['error_code'] ) ) {
        logTransaction( $twocheckoutConfig['paymentmethod'], $_GET, "Request error" );
        redirSystemURL( "action=invoices", "clientarea.php" );
    }
    $return .= "</head>\n<body>\n";
    $return .= "\n</body>\n</html>\n";
    echo $return;

} elseif ( isset( $_POST['REFNO'] ) && ! empty( $_POST['REFNO'] ) ) {
    $signature = $_POST["HASH"];
    $result    = "";

    // Verify Hash
    if ( isIpnResponseValid( $_POST, $twocheckoutConfig['secretKey'] ) ) {
        echo calculateIpnResponse( $_POST, $twocheckoutConfig['secretKey'] );

        // IPN for new recurring invoice
        if ( isset( $_POST["ORIGINAL_REFNOEXT"][0] ) && ! empty( $_POST["ORIGINAL_REFNOEXT"][0] ) && ( $_POST["FRAUD_STATUS"] == 'APPROVED' ) ) {
            $transactionId = $_POST["REFNO"];
            $externalRef   = $_POST["ORIGINAL_REFNOEXT"][0];
            $serviceId     = $_POST["IPN_EXTERNAL_REFERENCE"][0];
            $serviceId     = preg_replace( '/\D/', '', $serviceId );
            if (!empty($externalRef) && !empty($serviceId)) {
                checkCbTransID( $transactionId );
                $newInvoiceItem = (array) Capsule::table( 'tblinvoiceitems' )
                                                 ->join( 'tblinvoices', 'tblinvoiceitems.invoiceid', '=', 'tblinvoices.id' )
                                                 ->where( 'tblinvoiceitems.relid', $serviceId )
                                                 ->where( 'tblinvoices.status', 'Unpaid' )
                                                 ->first();
                $invoiceId      = $newInvoiceItem['invoiceid'];

                $orderData = TwocheckoutApiConvertPlus::callAPI( "GET", "orders/" . $transactionId . "/",
                    $twocheckoutConfig );

                $paymentAmount = 0;
                if ( ! empty( $_POST['IPN_PRICE'] ) ) {
                    foreach ( $_POST['IPN_PRICE'] as $priceAdd ) {
                        $paymentAmount = $paymentAmount + $priceAdd;
                    }
                }

                if ( ! empty( $invoiceId ) && in_array( $orderData['Status'], [ 'AUTHRECEIVED', 'COMPLETE' ] ) ) {
                    addInvoicePayment(
                        $invoiceId,
                        $transactionId,
                        $paymentAmount,
                        null,
                        $twocheckoutConfig['paymentmethod']
                    );
                }
            } else {
                logModuleCall($gatewayModuleName, 'error', '', 'Recurring 2Checkout transaction ' . $transactionId . ' IPN with no item external reference');
            }
            // IPN for any case other than recurring
        } else {
            if ( isset( $_POST["REFNOEXT"] ) && ! empty( $_POST["REFNOEXT"] ) && ( $_POST["FRAUD_STATUS"] == 'APPROVED' ) ) {
                //If no skip fraud then create invoice after fraud check passed
                if ( ! $skipFraud ) {
                    $transactionId = $_POST["REFNO"];
                    $invoiceId     = checkCbInvoiceID( $_POST["REFNOEXT"], $twocheckoutConfig['paymentmethod'] );
                    checkCbTransID( $transactionId );

                    addInvoicePayment(
                        $invoiceId,
                        $transactionId,
                        null,
                        null,
                        $twocheckoutConfig['paymentmethod']
                    );
                }
                logTransaction( $twocheckoutConfig['paymentmethod'], $_POST, 'Success' );
            } elseif ( isset( $_POST["REFNOEXT"] ) && ! empty( $_POST["REFNOEXT"] ) && ( $_POST["FRAUD_STATUS"] == 'DENIED' ) ) {
                logTransaction( $twocheckoutConfig['paymentmethod'], $_POST, 'Transaction DENIED' );
            }
        }
    } else {
        logModuleCall( $gatewayModuleName, 'error', '', 'Error. Cannot verify signature.' );
        echo '<EPAYMENT>Error. Cannot verify signature.</EPAYMENT>';
    }
}

function ArrayExpand( $array ) {
    $retval = "";
    for ( $i = 0; $i < sizeof( $array ); $i ++ ) {
        $size   = strlen( StripSlashes( $array[ $i ] ) );
        $retval .= $size . StripSlashes( $array[ $i ] );
    }

    return $retval;
}

function hmac( $key, $data ) {
    $b = 64;
    if ( strlen( $key ) > $b ) {
        $key = pack( "H*", md5( $key ) );
    }
    $key    = str_pad( $key, $b, chr( 0x00 ) );
    $ipad   = str_pad( '', $b, chr( 0x36 ) );
    $opad   = str_pad( '', $b, chr( 0x5c ) );
    $k_ipad = $key ^ $ipad;
    $k_opad = $key ^ $opad;

    return md5( $k_opad . pack( "H*", md5( $k_ipad . $data ) ) );
}

/**
 * @return bool
 */
function isIpnResponseValid( $params, $secret_key ) {
    $result       = '';
    $receivedHash = $params['HASH'];
    foreach ( $params as $key => $val ) {

        if ( $key != "HASH" ) {
            if ( is_array( $val ) ) {
                $result .= ArrayExpand( $val );
            } else {
                $size   = strlen( stripslashes( $val ) );
                $result .= $size . stripslashes( $val );
            }
        }
    }
    if ( isset( $params['REFNO'] ) && ! empty( $params['REFNO'] ) ) {
        $calcHash = hmac( $secret_key, $result );
        if ( $receivedHash === $calcHash ) {
            return true;
        }
    }

    return false;
}

/**
 * @param $ipn_params
 * @param $secret_key
 *
 * @return string
 */
function calculateIpnResponse( $params, $secret_key ) {
    $resultResponse    = '';
    $ipnParamsResponse = [];
    // we're assuming that these always exist, if they don't then the problem is on avangate side
    $ipnParamsResponse['IPN_PID'][0]   = $params['IPN_PID'][0];
    $ipnParamsResponse['IPN_PNAME'][0] = $params['IPN_PNAME'][0];
    $ipnParamsResponse['IPN_DATE']     = $params['IPN_DATE'];
    $ipnParamsResponse['DATE']         = date( 'YmdHis' );

    foreach ( $ipnParamsResponse as $key => $val ) {
        $resultResponse .= ArrayExpand( (array) $val );
    }

    return sprintf(
        '<EPAYMENT>%s|%s</EPAYMENT>',
        $ipnParamsResponse['DATE'],
        hmac( $secret_key, $resultResponse )
    );
}
