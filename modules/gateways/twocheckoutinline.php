<?php
/**
 * Payment Gateway modules allow you to integrate payment solutions from 2Checkout (INLINE) with the
 * WHMCS platform.
 */

use WHMCS\Database\Capsule;
use WHMCS\Billing\Invoice;

require_once realpath(dirname(__FILE__)) . "/twocheckout/lib/TwocheckoutHelper.php";
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * @return string[]
 */
function twocheckoutinline_MetaData()
{
    return [
        'DisplayName' => '2Checkout Inline',
        'APIVersion' => '1.1',
    ];
}

/**
 * @return array
 */
function twocheckoutinline_config()
{
    return TwocheckoutHelper::config('2Checkout  Inline', \App::getSystemURL() . 'modules/gateways/callback/twocheckout-ipn.php?tco_type=inline');
}

/**
 * @param $params
 *
 * @return string
 */
function twocheckoutinline_link($params)
{
    $invoice = Invoice::find($params['invoiceid']);
    $client = $params['clientdetails'];
    $products = $invoice->getBillingValues();
    $coupons = $invoice->items->toArray();
    unset($products['overdue']);
    $billingAddressData = _getBillingAddress($client);
    $shippingAddressData = _getShippingAddress($client);
    $itemsArray = [];
    $creditOrVoucher = false;
    foreach ($products as $item) {
        $lineItem = [];
        $lineItemAmount = (array_key_exists('firstPaymentAmount',
            $item)) ? $item['firstPaymentAmount'] : $item['amount'];
        if ($lineItemAmount > 0) {
            $lineItem["quantity"] = 1;
            $lineItem["price"] = abs($item['amount']);
            $lineItem["name"] = htmlspecialchars_decode(StripSlashes($item['description']), ENT_COMPAT | ENT_QUOTES);
            $lineItem["tangible"] = 0;
            $lineItem["type"] = "PRODUCT";
            $serviceId = (int)preg_replace('/\D/', '', $item['itemId']);

            $lineItem["reference"]["external"]["item"] = $item['itemId'];
            if ($item['recurringCyclePeriod'] && $item['recurringCyclePeriod'] > 0) {
                $lineItem["recurrence"] = [
                    'unit' => TwocheckoutHelper::mapRecurringUnit($item['recurringCycleUnits']),
                    'length' => $item['recurringCyclePeriod']
                ];
                $lineItem["duration"] = [
                    'unit' => 'FOREVER',
                    'length' => 1
                ];
                $lineItem['renewal-price'] = abs($item['amount']);
            }

            //work around for recurring products with nonrecurring promotions
            foreach ($coupons as $product) {
                if ((isset($product['relid']) && $product['relid'] === $serviceId) &&
                    (isset($product['service']) && $product['service']) && $product['amount'] > 0) {
                    $promotion = Capsule::table('tblpromotions')->find($product['service']['promoid']);
                    if ($promotion && $promotion->recurring === 0 && $product) {
                        $lineItem['price'] = abs($product['service']['firstpaymentamount']);
                    }
                }
            }
        }
        array_push($itemsArray, $lineItem);
    }

    // Add credit as coupon if present
    if ($invoice->credit > 0) {
        $lineItem["quantity"] = 1;
        $lineItem["name"] = 'Credit';
        $lineItem["tangible"] = 0;
        $lineItem["type"] = "COUPON";
        $lineItem["price"] = abs($invoice->credit);
        $lineItem['item-ext-ref'] = 'Credit';
        array_push($itemsArray, $lineItem);
    }

    $inlineParams['error'] = $creditOrVoucher;
    $inlineParams['payment_method'] = $params['name'];
    $inlineParams['products'] = $itemsArray;
    $inlineParams['currency'] = strtolower($params['currency']);
    $inlineParams['language'] = $params['language'] ?? 'en';
    $inlineParams['return-method'] = [
        'type' => 'redirect',
        'url' => $params["systemurl"] . 'modules/gateways/callback/twocheckout-ipn.php?tco_type=inline'
    ];
    $inlineParams['test'] = $params['testMode'] === 'on' ? 1 : 0;
    $inlineParams['order-ext-ref'] = $params['invoiceid'];
    $inlineParams['customer-ext-ref'] = $client['email'];
    $inlineParams['src'] = TwocheckoutHelper::getFormattedVersion($params['whmcsVersion']);
    $inlineParams['dynamic'] = 1;
    $inlineParams['merchant'] = $params['accountId'];
    $inlineParams['shipping_address'] = ($shippingAddressData);
    $inlineParams['billing_address'] = ($billingAddressData);
    $inlineParams['customization'] = ( $params['inlineTemplate'] === 'Multi step inline' ? 'inline' : ( $params['inlineTemplate'] === 'One step inline' ? 'inline-one-step' : '' ) );

    if (isset($client['companyname'])) {
        $inlineParams['company-name'] = $client['companyname'];
    }

    $inlineParams['signature'] = TwocheckoutHelper::getSignature($params['accountId'], $params['secretWord'], $inlineParams);
    $inlineParams['products'] = _prepareProducts($itemsArray);
    $assetHelper = DI::make('asset');
    $jsUrl = $assetHelper->getWebRoot() . '/modules/gateways/twocheckout/twocheckoutinline.js';
    if (!isset($_GET['pendingreview']) && !$_GET['pendingreview']) {
        return '<script> let payload = ' . json_encode($inlineParams) . ';</script>
        <script type="text/javascript" src="' . $jsUrl . '"></script>
        <script type="text/javascript">var buttonClicked = false, noAutoSubmit = true</script>
        <button class="btn btn-success btn-sm" onclick="runInlineCart(payload)">' . $params['langpaynow'] . '</button>';
    }
}

/**
 * @param $params
 *
 * @return array
 * @throws \Exception
 */
function twocheckoutinline_cancelSubscription($params)
{
    $twocheckoutConfig = [
        "accountId" => $params['accountId'],
        "secretKey" => $params['secretKey']
    ];
    TwocheckoutHelper::callAPI('DELETE', "subscriptions/{$params['subscriptionID']}/", $twocheckoutConfig);
    return [
        'status' => 'success',
        'rawdata' => ['Subscription with 2Checkout reference: ' . $params['subscriptionID'] . ' was canceled!']
    ];
}


function twocheckoutinline_refund($params)
{
    return TwocheckoutHelper::refund($params, $_POST);
}


/**
 * we have to change (after we generate the signature) the param
 * from 'renewal-price' -> 'renewalPrice' in order to work on client side along with the signature
 *
 * @param $products
 *
 * @return array
 */
function _prepareProducts($products)
{
    $items = [];
    foreach ($products as $product) {
        if (isset($product['renewal-price'])) {
            $product['renewalPrice'] = $product['renewal-price'];
            unset($product['renewal-price']);
        }
        if (isset($product["reference"]["external"]["item"])) {
            $product['externalReference'] = $product["reference"]["external"]["item"];
            unset($product["reference"]);
        }
        $items[] = $product;
    }

    return $items;
}

/**
 * get client's billing address
 *
 * @param $client
 *
 * @return array
 */
function _getBillingAddress($client)
{
    return [
        'name' => $client['firstname'] . ' ' . $client['lastname'],
        'phone' => $client['phonenumber'],
        'country' => $client['country'],
        'state' => $client['state'],
        'email' => $client['email'],
        'address' => $client['address1'],
        'address2' => !empty($client['address2']) ? $client['address2'] : '',
        'city' => $client['city'],
        'zip' => $client['postcode'],
    ];
}

/**
 * get client's shipping address
 *
 * @param $client
 *
 * @return array
 */
function _getShippingAddress($client)
{
    return [
        'ship-name' => $client['firstname'] . ' ' . $client['lastname'],
        'ship-country' => $client['country'],
        'ship-state' => $client['state'],
        'ship-city' => $client['city'],
        'ship-email' => $client['email'],
        'ship-address' => $client['address1'],
        'ship-address2' => !empty($client['address2']) ? $client['address2'] : '',
    ];
}

