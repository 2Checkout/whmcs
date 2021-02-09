<?php
/**
 * Payment Gateway modules allow you to integrate payment solutions from 2Checkout (INLINE) with the
 * WHMCS platform.
 */

use WHMCS\Billing\Invoice;

require_once realpath( dirname( __FILE__ ) ) . "/twocheckoutinline/lib/TwocheckoutApiInline.php";
if ( ! defined( "WHMCS" ) ) {
    die( "This file cannot be accessed directly" );
}

/**
 * @return string[]
 */
function twocheckoutinline_MetaData() {
    return [
        'DisplayName' => '2Checkout Inline',
        'APIVersion'  => '1.1',
    ];
}

/**
 * @return array
 */
function twocheckoutinline_config() {
    return [
        'FriendlyName'  => [
            'Type'  => 'System',
            'Value' => '2Checkout  Inline',
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
            'Default'      => \App::getSystemURL() . 'modules/gateways/callback/twocheckoutinline.php',
            'Description'  => 'Copy this link to your 2checkout account in the IPN section (check the documentation)!',
        ],
        'refundReasons' => [
            'FriendlyName' => 'Reasons for Refunds',
            'Type'         => 'textarea',
            'Rows'         => '10',
            'Cols'         => '30',
            'Description'  => 'Enter the reasons for refunds that you have <a target="_blank" href="https://knowledgecenter.2checkout.com/Documentation/27Refunds_and_chargebacks/Refunds#Adding_custom_refund_reasons">setup in your 2Checkout account</a>.',
        ]
    ];
}

/**
 * get client's billing address
 *
 * @param $client
 *
 * @return array
 */
function _getBillingAddress( $client ) {
    return [
        'name'         => $client['firstname'] . ' ' . $client['lastname'],
        'phone'        => $client['phonenumber'],
        'country'      => $client['country'],
        'state'        => $client['state'],
        'email'        => $client['email'],
        'address'      => $client['address1'],
        'address2' => ! empty( $client['address2'] ) ? $client['address2'] : '',
        'city'         => $client['city'],
        'zip'          => $client['postcode'],
    ];
}

/**
 * get client's shipping address
 *
 * @param $client
 *
 * @return array
 */
function _getShippingAddress( $client ) {
    return [
        'ship-name'     => $client['firstname'] . ' ' . $client['lastname'],
        'ship-country'  => $client['country'],
        'ship-state'    => $client['state'],
        'ship-city'     => $client['city'],
        'ship-email'    => $client['email'],
        'ship-address'  => $client['address1'],
        'ship-address2' => ! empty( $client['address2'] ) ? $client['address2'] : '',
    ];
}


/**
 * @param $params
 *
 * @return string
 */
function twocheckoutinline_link( $params ) {
    $invoice  = Invoice::find( $params['invoiceid'] );
    $client   = $params['clientdetails'];
    $products = $invoice->getBillingValues();
    unset( $products['overdue'] );

    $billingAddressData  = _getBillingAddress( $client );
    $shippingAddressData = _getShippingAddress( $client );
    $itemsArray          = [];
    $creditOrVoucher     = false;
    foreach ( $products as $item ) {
        $lineItem       = [];
        $lineItemAmount = ( array_key_exists( 'firstPaymentAmount',
            $item ) ) ? $item['firstPaymentAmount'] : $item['lineItemAmount'];
        if ( $lineItemAmount > 0 ) {
            $lineItem["quantity"] = 1;
            $lineItem["name"]     = $item['description'];
            $lineItem["tangible"] = 0;
            $lineItem["type"]     = "PRODUCT";
            $lineItem["price"]    = abs( $lineItemAmount );
            if ( $item['recurringCyclePeriod'] && $item['recurringCyclePeriod'] > 0 ) {
                $lineItem["recurrence"]    = [
                    'unit'   => _mapRecurringUnitInline( $item['recurringCycleUnits'] ),
                    'length' => $item['recurringCyclePeriod']
                ];
                $lineItem["duration"]      = [
                    'unit'   => 'FOREVER',
                    'length' => 1
                ];
                $lineItem['renewal-price'] = abs( $lineItemAmount );
            }
        } else {
            $creditOrVoucher = true;
        }
        array_push( $itemsArray, $lineItem );
    }
    // Add credit as coupon if present
    if ( $invoice->credit > 0 ) {
        $creditOrVoucher = true;
    }

    $inlineParams['error']            = $creditOrVoucher;
    $inlineParams['payment_method']   = $params['name'];
    $inlineParams['products']         = $itemsArray;
    $inlineParams['currency']         = strtolower( $params['currency'] );
    $inlineParams['language']         = $params['language'] ?? 'en';
    $inlineParams['return-method']    = [
        'type' => 'redirect',
        'url'  => $params["systemurl"] . 'modules/gateways/callback/' . $params["paymentmethod"] . '.php'
    ];
    $inlineParams['test']             = $params['testMode'] === 'on' ? 1 : 0;
    $inlineParams['order-ext-ref']    = $params['invoiceid'];
    $inlineParams['customer-ext-ref'] = $client['email'];
    $inlineParams['src']              = 'WHMCS_' . $params['whmcsVersion'];
    $inlineParams['dynamic']          = 1;
    $inlineParams['merchant']         = $params['accountId'];
    $inlineParams['shipping_address'] = ( $shippingAddressData );
    $inlineParams['billing_address']  = ( $billingAddressData );

    if ( isset( $client['companyname'] ) ) {
        $inlineParams['company-name'] = $client['companyname'];
    }

    $inlineParams['signature'] = TwocheckoutApiInline::getInlineSignature( $params['accountId'],
        $params['secretWord'], $inlineParams );
    $inlineParams['products']  = _prepareProducts( $itemsArray );

    $assetHelper = DI::make( 'asset' );
    $jsUrl       = $assetHelper->getWebRoot() . '/modules/gateways/twocheckoutinline/twocheckoutinline.js';

    if ( ! isset( $_GET['pendingreview'] ) && ! $_GET['pendingreview'] ) {
        return '<script> let payload = ' . json_encode( $inlineParams ) . ';</script>
<script type="text/javascript" src="' . $jsUrl . '"></script>
<script type="text/javascript">var buttonClicked = false, noAutoSubmit = true</script>
<button class="btn btn-success btn-sm" onclick="runInlineCart(payload)">' . $params['langpaynow'] . '</button>';
    }
}

/**
 * we have to change (after we generate the signature) the param
 * from 'renewal-price' -> 'renewalPrice' in order to work on client side along with the signature
 *
 * @param $products
 *
 * @return array
 */
function _prepareProducts( $products ) {
    $items = [];
    foreach ( $products as $product ) {
        if ( isset( $product['renewal-price'] ) ) {
            $product['renewalPrice'] = $product['renewal-price'];
            unset( $product['renewal-price'] );
        }
        $items[] = $product;
    }

    return $items;
}

/**
 * @param $params
 *
 * @return array
 * @throws \Exception
 */
function twocheckoutinline_refund( $params ) {
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

    $orderData = TwocheckoutApi::callAPI( "GET", "orders/{$params['transid']}/", $twocheckoutConfig );

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
        $responseData = TwocheckoutApiInline::callAPI( "POST", "orders/{$params['transid']}/refund/",
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

function _mapRecurringUnitInline( $unit ) {
    $recurringUnits = [
        "Days"   => "DAY",
        "Weeks"  => "WEEK",
        "Months" => "MONTH",
        "Years"  => "YEAR"
    ];

    return $recurringUnits[ $unit ];
}
