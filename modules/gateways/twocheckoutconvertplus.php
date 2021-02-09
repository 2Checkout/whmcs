<?php

use WHMCS\Billing\Invoice;

require_once realpath( dirname( __FILE__ ) ) . "/twocheckoutconvertplus/lib/ConvertPlusHelper.php";
require_once realpath( dirname( __FILE__ ) ) . "/twocheckoutconvertplus/lib/TwocheckoutApiConvertPlus.php";
if ( ! defined( "WHMCS" ) ) {
    die( "This file cannot be accessed directly" );
}

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related capabilities and
 * settings.
 *
 * @see https://developers.whmcs.com/payment-gateways/meta-data-params/
 *
 * @return array
 */
function twocheckoutconvertplus_MetaData() {
    return [
        'DisplayName' => '2Checkout Convert Plus',
        'APIVersion'  => '1.0',
    ];
}


function twocheckoutconvertplus_config() {
    return [
        'FriendlyName'  => [
            'Type'  => 'System',
            'Value' => '2Checkout Convert Plus',
        ],
        'accountId'     => [
            'FriendlyName' => 'Merchant Code',
            'Type'         => 'text',
            'Size'         => '30',
            'Default'      => '',
            'Description'  => 'Enter your Merchant Code here',
        ],
        'secretWord'    => [
            'FriendlyName' => 'Secret Word',
            'Type'         => 'password',
            'Size'         => '50',
            'Default'      => '',
            'Description'  => 'Enter secret word here',
        ],
        'secretKey'     => [
            'FriendlyName' => 'Secret Key',
            'Type'         => 'password',
            'Size'         => '50',
            'Default'      => '',
            'Description'  => 'Enter secret key here',
        ],
        'testMode'      => [
            'FriendlyName' => 'Test Mode',
            'Type'         => 'yesno',
            'Description'  => 'Tick to enable test mode',
        ],
        'skipFraud'     => [
            'FriendlyName' => 'Skip 2CO Fraud Check',
            'Type'         => 'yesno',
            'Description'  => 'Tick to mark invoices as paid without waiting for 2Checkout fraud review.',
        ],
        'inpUrl'        => [
            'FriendlyName' => 'IPN URL',
            'Type'         => 'text',
            'Size'         => '65',
            'ReadOnly'     => true,
            'Default'      => \App::getSystemURL() . 'modules/gateways/callback/twocheckoutconvertplus.php',
            'Description'  => 'Copy this link to your 2checkout account in the IPN section (check the documentation)!',
        ],
        'refundReasons' => [
            'FriendlyName' => 'Reasons for Refunds',
            'Type'         => 'textarea',
            'Rows'         => '10',
            'Cols'         => '30',
            'Default'      => 'Other',
            'Description'  => 'Enter the reasons for refunds that you have <a target="_blank" href="https://knowledgecenter.2checkout.com/Documentation/27Refunds_and_chargebacks/Refunds#Adding_custom_refund_reasons">setup in your 2Checkout account</a>.',
        ]
    ];
}

/**
 * Payment link.
 *
 * Required by third party payment gateway modules only.
 *
 * Defines the HTML output displayed on an invoice. Typically consists of an
 * HTML form that will take the user to the payment gateway endpoint.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 */
function twocheckoutconvertplus_link( $params ) {
    if ( $params['paymentmethod'] !== 'twocheckoutconvertplus' ) {
        return false;
    }

// Gateway Configuration Parameters
    $accountId  = $params['accountId'];
    $secretWord = htmlspecialchars_decode( $params['secretWord'] );
    $testMode   = $params['testMode'];
    $withTaxes  = $params['withTaxes'];

    // Invoice Parameters
    $currencyCode = $params['currency'];
    $invoice      = Invoice::find( $params['invoiceid'] );
    $products     = $invoice->getBillingValues();

    // System Parameters
    $systemUrl  = $params['systemurl'];
    $moduleName = $params['paymentmethod'];
    $client     = $params['clientdetails'];

    $orderNumber   = $params['invoiceid'];
    $langPayNow    = $params['langpaynow'];
    $buyLinkParams = [];

    $buyLinkParams['name']         = $client['firstname'] . ' ' . $client['lastname'];
    $buyLinkParams['phone']        = $client['phonenumber'];
    $buyLinkParams['country']      = $client['country'];
    $buyLinkParams['state']        = $client['state'];
    $buyLinkParams['email']        = $client['email'];
    $buyLinkParams['address']      = $client['address1'];
    $buyLinkParams['address2']     = ! empty( $client['address2'] ) ? $client['address2'] : '';
    $buyLinkParams['city']         = $client['city'];

    if ( isset( $client['companyname'] ) ) {
	    $buyLinkParams['company-name'] = $client['companyname'];
    }

    $buyLinkParams['ship-name']    = $client['firstname'] . ' ' . $client['lastname'];
    $buyLinkParams['ship-country'] = $client['country'];
    $buyLinkParams['ship-state']   = $client['state'];

    $buyLinkParams['ship-city']     = $client['city'];
    $buyLinkParams['ship-email']    = $client['email'];
    $buyLinkParams['ship-address']  = $client['address1'];
    $buyLinkParams['ship-address2'] = ! empty( $client['address2'] ) ? $client['address2'] : '';
    $buyLinkParams['zip']           = $client['postcode'];

    //Prepare Products structure
    $itemsArray = [];
    foreach ( $products as $item ) {
        $lineItemAmount = ( array_key_exists( 'firstPaymentAmount',
            $item ) ? $item['firstPaymentAmount'] : $item['lineItemAmount'] );
        if ( abs( $lineItemAmount ) > 0 ) {
            $itemsArray["qty"][]      = 1;
            $itemsArray["prod"][]     = $item['description'];
            $itemsArray["tangible"][] = 0;
            $itemsArray["type"][]     = "PRODUCT";
            $itemsArray["price"][]    = abs( $lineItemAmount );

            $itemsArray["recurrence"][] = ( isset( $item['recurringCyclePeriod'] ) && isset( $item['recurringCycleUnits'] ) ) ?
                $item['recurringCyclePeriod'] . ':' . _mapRecurringUnit( $item['recurringCycleUnits'] )
                : '';

            $itemsArray["duration"][]      = ( isset( $item['recurringCyclePeriod'] ) && isset( $item['recurringCycleUnits'] ) ) ? '1:' . 'FOREVER' : '';
            $itemsArray['renewal-price'][] = abs( $lineItemAmount );

        }
    }

    $buyLinkParams['prod']     = implode( ';', $itemsArray["prod"] );
    $buyLinkParams['price']    = implode( ';', $itemsArray["price"] );
    $buyLinkParams['qty']      = implode( ';', $itemsArray["qty"] );
    $buyLinkParams['type']     = implode( ';', $itemsArray["type"] );
    $buyLinkParams['tangible'] = implode( ';', $itemsArray["tangible"] );

    if ( isset( $itemsArray["recurrence"] ) ) {
        $buyLinkParams['recurrence'] = implode( ';', $itemsArray["recurrence"] );
    }
    if ( isset( $itemsArray["duration"] ) ) {
        $buyLinkParams['duration'] = implode( ';', $itemsArray["duration"] );
    }
    if ( isset( $itemsArray["renewal-price"] ) ) {
        $buyLinkParams['renewal-price'] = implode( ';', $itemsArray["renewal-price"] );
    }

    $buyLinkParams['src'] = 'WHMCS_' . $params['whmcsVersion'];

    // url NEEDS a protocol(http or https)
    $buyLinkParams['return-type']      = 'redirect';
    $customReturnUrl                   = $systemUrl . 'modules/gateways/callback/' . $moduleName . '.php';
    $buyLinkParams['return-url']       = $customReturnUrl;
    $buyLinkParams['expiration']       = time() + ( 3600 * 5 );
    $buyLinkParams['order-ext-ref']    = $orderNumber;
    $buyLinkParams['customer-ext-ref'] = $client['email'];
    $buyLinkParams['currency']         = strtolower( $currencyCode );

    $buyLinkParams['test'] = $testMode === 'on' ? 1 : 0;
    // account id in this case is the merchant code
    $buyLinkParams['merchant'] = $accountId;
    $buyLinkParams['dynamic']  = 1;

    $helper                     = new ConvertPlusHelper();
    $buyLinkParams['signature'] = $helper->getSignature(
        $accountId,
        $secretWord,
        $buyLinkParams
    );
    $url                        = "https://secure.2checkout.com/checkout/buy";

    if ( $buyLinkParams['signature'] ) {
        $htmlOutput = '<form method="get" action="' . $url . '">';
        foreach ( $buyLinkParams as $input_name => $value ) {
            $htmlOutput .= '<input type="hidden" name="' . $input_name . '" value="' . $value . '" >';
        }
        $htmlOutput .= '<input type="submit" class="btn btn-success btn-sm" value="' . $langPayNow . '" />';
        $htmlOutput .= '</form>';

        return $htmlOutput;
    } else {
        return "<div><p>Ups, something went wrong! Please try again.</p></div>";
    }
}

if ( ! function_exists( '_mapRecurringUnit' ) ) {
    function _mapRecurringUnit( $unit ) {
        $recurringUnits = [
            "Days"   => "DAY",
            "Weeks"  => "WEEK",
            "Months" => "MONTH",
            "Years"  => "YEAR"
        ];

        return $recurringUnits[ $unit ];
    }
}

/**
 * Refund transaction.
 *
 * Called when a refund is requested for a previously successful transaction.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return array Transaction response status
 * @see https://developers.whmcs.com/payment-gateways/refunds/
 *
 */
function twocheckoutconvertplus_refund( $params ) {

    // Gateway Configuration Parameters
    $twocheckoutConfig = [
        "accountId" => $params['accountId'],
        "secretKey" => $params['secretKey']
    ];

    // Refund Reason
    if ( isset( $_POST['reason'] ) && ! empty( $_POST['reason'] ) ) {
        $refundReason = $_POST['reason'];
    } else {
        $refundReason = 'Cancellation';
    }

    // Refund Comment
    if ( isset( $_POST['comment'] ) && ! empty( $_POST['comment'] ) ) {
        $refundComment = $_POST['comment'];
    } else {
        $refundComment = '';
    }

    $orderData = TwocheckoutApiConvertPlus::callAPI( "GET", "orders/{$params['transid']}/", $twocheckoutConfig );
    if ( $params['amount'] == $orderData["GrossPrice"] ) {
        // Refund Details
        $refundDetails = [
            "amount"  => $params['amount'],
            "comment" => $refundComment,
            "reason"  => $refundReason
        ];
    } else {
        $lineItems = $orderData["Items"];
        usort( $lineItems, "cmpPrices" );
        $lineitemReference = $lineItems[0]["LineItemReference"];
        if ( $lineItems[0]['Price']['GrossPrice'] >= $params['amount'] ) {
            // Refund Item Details
            $itemsArray[] = [
                "Quantity"          => "1",
                "LineItemReference" => $lineitemReference,
                "Amount"            => $params['amount']
            ];

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
        $responseData = TwocheckoutApiConvertPlus::callAPI( "POST", "orders/{$params['transid']}/refund/",
            $twocheckoutConfig, $refundDetails );

        if ( ! isset( $responseData['error_code'] ) && $responseData == '1' ) {
            $returnData = [
                'status'  => 'success',
                'transid' => $params['transid'],
                'rawdata' => $responseData,
            ];
        } else {
            $returnData = [
                'status'  => 'declined',
                'transid' => $params['transid'],
                'rawdata' => $responseData,
            ];
        }
    } catch ( Exception $e ) {
        $returnData = [
            'status'  => 'declined',
            'transid' => $params['transid']
        ];
    }

    return $returnData;
}

