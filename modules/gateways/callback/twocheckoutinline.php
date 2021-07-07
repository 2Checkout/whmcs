<?php
/**
 * API calls to 2Checkout for Inline Cart
 * IPN & redirect URL
 */

use WHMCS\Database\Capsule;
use WHMCS\Billing\Invoice;

require_once realpath( dirname( __FILE__ ) ) . "/../twocheckoutinline/lib/TwocheckoutApiInline.php";
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
    $orderData = TwocheckoutApiInline::callAPI( "GET", "orders/" . $_GET['refno'] . "/", $twocheckoutConfig );
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
    ob_start();
    while ( list( $key, $val ) = each( $_POST ) ) {
        $$key = $val;
        /* get values */
        if ( $key != "HASH" ) {
            if ( is_array( $val ) ) {
                $result .= ArrayExpand( $val );
            } else {
                $size   = strlen( StripSlashes( $val ) );
                $result .= $size . StripSlashes( $val );
            }
        }
    }
    $body = ob_get_contents();
    ob_end_flush();

    $date_return = date( "YmdGis" );
    $return      = strlen( $_POST["IPN_PID"][0] ) . $_POST["IPN_PID"][0] . strlen( $_POST["IPN_PNAME"][0] ) . $_POST["IPN_PNAME"][0];
    $return      .= strlen( $_POST["IPN_DATE"] ) . $_POST["IPN_DATE"] . strlen( $date_return ) . $date_return;
    $hash        = hmac( $twocheckoutConfig['secretKey'], $result );
    $body        .= $result . "\r\n\r\nHash: " . $hash . "\r\n\r\nSignature: " . $signature . "\r\n\r\nReturnSTR: " . $return;

    if ( $hash == $signature ) {
        echo "Verified OK!";
        $result_hash = hmac( $twocheckoutConfig['secretKey'], $return );
        echo "<EPAYMENT>" . $date_return . "|" . $result_hash . "</EPAYMENT>";

        // IPN for new recurring invoice
        if ( isset( $_POST["ORIGINAL_REFNOEXT"][0] ) && ! empty( $_POST["ORIGINAL_REFNOEXT"][0] ) && $_POST["FRAUD_STATUS"] == 'APPROVED' ) {
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
                $orderData      = TwocheckoutApiInline::callAPI( "GET", "orders/" . $transactionId . "/",
                    $twocheckoutConfig );
                $paymentAmount  = 0;
                if ( ! empty( $_POST['IPN_PRICE'] ) ) {
                    foreach ( $_POST['IPN_PRICE'] as $priceAdd ) {
                        $paymentAmount = $paymentAmount + $priceAdd;
                    }
                }
                if ( ! empty( $invoiceId ) && in_array( $orderData['Status'], [ 'AUTHRECEIVED', 'COMPLETE' ] ) ) {
                    addInvoicePayment( $invoiceId, $transactionId, $paymentAmount, null, $twocheckoutConfig['name'] );
                }
            } else {
                logModuleCall($gatewayModuleName, 'error', '', 'Recurring 2Checkout transaction ' . $transactionId . ' IPN with no item external reference');
            }
            // IPN for any case other than recurring
        } else {
            if ( isset( $_POST["REFNOEXT"] ) && ! empty( $_POST["REFNOEXT"] ) && $_POST["FRAUD_STATUS"] == 'APPROVED' ) {
                if ( ! $skipFraud ) {
                    $transactionId = $_POST["REFNO"];
                    $invoiceId     = checkCbInvoiceID( $_POST["REFNOEXT"], $twocheckoutConfig['name'] );
                    checkCbTransID( $transactionId );
                    addInvoicePayment( $invoiceId, $transactionId, null, null, 'twocheckoutinline' );
                }
                logTransaction( $twocheckoutConfig['name'], $_POST, 'Success' );
            } elseif ( isset( $_POST["REFNOEXT"] ) && ! empty( $_POST["REFNOEXT"] ) && ( $_POST["FRAUD_STATUS"] == 'DENIED' ) ) {
                logTransaction( $twocheckoutConfig['paymentmethod'], $_POST, 'Transaction DENIED' );
            }
        }
    } else {
        logModuleCall( $gatewayModuleName, 'error', '', $body );
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
