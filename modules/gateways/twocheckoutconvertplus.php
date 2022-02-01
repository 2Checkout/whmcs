<?php

use WHMCS\Billing\Invoice;
use WHMCS\Database\Capsule;

require_once realpath(dirname(__FILE__)) . "/twocheckout/lib/TwocheckoutHelper.php";
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
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
function twocheckoutconvertplus_MetaData()
{
    return [
        'DisplayName' => '2Checkout Convert Plus',
        'APIVersion' => '1.0',
    ];
}


function twocheckoutconvertplus_config()
{
    return TwocheckoutHelper::config('2Checkout Convert Plus', \App::getSystemURL() . 'modules/gateways/callback/twocheckout-ipn.php?tco_type=convertplus');
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
function twocheckoutconvertplus_link($params)
{
    if ($params['paymentmethod'] !== 'twocheckoutconvertplus') {
        return false;
    }

// Gateway Configuration Parameters
    $accountId = $params['accountId'];
    $secretWord = htmlspecialchars_decode($params['secretWord']);
    $testMode = $params['testMode'];

    // Invoice Parameters
    $currencyCode = $params['currency'];
    $invoice = Invoice::find($params['invoiceid']);
    $products = $invoice->getBillingValues();
    $coupons = $invoice->items->toArray();
    // System Parameters
    $systemUrl = $params['systemurl'];
    $client = $params['clientdetails'];

    $orderNumber = $params['invoiceid'];
    $langPayNow = $params['langpaynow'];
    $buyLinkParams = [];
    $customReturnUrl = $systemUrl . 'modules/gateways/callback/twocheckout-ipn.php?tco_type=convertplus';

    $buyLinkParams['name'] = $client['firstname'] . ' ' . $client['lastname'];
    $buyLinkParams['phone'] = $client['phonenumber'];
    $buyLinkParams['country'] = $client['country'];
    $buyLinkParams['state'] = $client['state'];
    $buyLinkParams['email'] = $client['email'];
    $buyLinkParams['address'] = $client['address1'];
    $buyLinkParams['address2'] = !empty($client['address2']) ? $client['address2'] : '';
    $buyLinkParams['city'] = $client['city'];
    $buyLinkParams['ship-name'] = $client['firstname'] . ' ' . $client['lastname'];
    $buyLinkParams['ship-country'] = $client['country'];
    $buyLinkParams['ship-state'] = $client['state'];
    $buyLinkParams['ship-city'] = $client['city'];
    $buyLinkParams['ship-email'] = $client['email'];
    $buyLinkParams['ship-address'] = $client['address1'];
    $buyLinkParams['ship-address2'] = !empty($client['address2']) ? $client['address2'] : '';
    $buyLinkParams['zip'] = $client['postcode'];
    if (isset($client['companyname'])) {
        $buyLinkParams['company-name'] = $client['companyname'];
    }

    //Prepare Products structure
    $itemsArray = [];
    foreach ($products as $item) {
        $lineItemAmount = (array_key_exists('firstPaymentAmount',
            $item) ? $item['firstPaymentAmount'] : $item['amount']);
        if (abs($lineItemAmount) > 0) {
            $itemsArray["qty"][] = 1;
            $itemsArray["prod"][] = htmlspecialchars_decode(StripSlashes($item['description']), ENT_COMPAT | ENT_QUOTES);
            $itemsArray["tangible"][] = 0;
            $itemsArray["type"][] = "PRODUCT";
            $itemsArray["duration"][] = (isset($item['recurringCyclePeriod']) && isset($item['recurringCycleUnits'])) ? '1:' . 'FOREVER' : '';
            $itemsArray['renewal-price'][] = abs($lineItemAmount);
            $itemsArray['item-ext-ref'][] = $item['itemId'];
            $itemsArray["recurrence"][] = (isset($item['recurringCyclePeriod']) && isset($item['recurringCycleUnits'])) ?
                $item['recurringCyclePeriod'] . ':' . TwocheckoutHelper::mapRecurringUnit($item['recurringCycleUnits'])
                : '';
            $serviceId = (int)preg_replace('/\D/', '', $item['itemId']);
            $price = abs($lineItemAmount);

            //work around for recurring products with nonrecurring promotions
            foreach ($coupons as $product) {
                if ((isset($product['relid']) && $product['relid'] === $serviceId) &&
                    (isset($product['service']) && $product['service']) && $product['amount'] > 0) {
                    $promotion = Capsule::table('tblpromotions')->find($product['service']['promoid']);
                    if ($promotion && $promotion->recurring === 0 && $product) {
                        $price= abs($product['service']['firstpaymentamount']);
                    }
                }
            }
            $itemsArray["price"][] = $price;

        }
    }
    // Add credit as coupon if present
    if ($invoice->credit > 0) {
        $itemsArray["qty"][] = 1;
        $itemsArray["prod"][] = 'Credit';
        $itemsArray["tangible"][] = 0;
        $itemsArray["type"][] = "COUPON";
        $itemsArray["price"][] = abs($invoice->credit);
        $itemsArray["recurrence"][] = '';
        $itemsArray["duration"][] = '';
        $itemsArray['renewal-price'][] = 0;
        $itemsArray['item-ext-ref'][] = 'Credit';
    }

    $buyLinkParams['prod'] = implode(';', $itemsArray["prod"]);
    $buyLinkParams['item-ext-ref'] = implode(';', $itemsArray["item-ext-ref"]);
    $buyLinkParams['price'] = implode(';', $itemsArray["price"]);
    $buyLinkParams['qty'] = implode(';', $itemsArray["qty"]);
    $buyLinkParams['type'] = implode(';', $itemsArray["type"]);
    $buyLinkParams['tangible'] = implode(';', $itemsArray["tangible"]);

    if (isset($itemsArray["recurrence"])) {
        $buyLinkParams['recurrence'] = implode(';', $itemsArray["recurrence"]);
    }
    if (isset($itemsArray["duration"])) {
        $buyLinkParams['duration'] = implode(';', $itemsArray["duration"]);
    }
    if (isset($itemsArray["renewal-price"])) {
        $buyLinkParams['renewal-price'] = implode(';', $itemsArray["renewal-price"]);
    }

    $buyLinkParams['src'] = TwocheckoutHelper::getFormattedVersion($params['whmcsVersion']);
    $buyLinkParams['return-type'] = 'redirect';
    $buyLinkParams['return-url'] = $customReturnUrl;
    $buyLinkParams['expiration'] = time() + (3600 * 5);
    $buyLinkParams['order-ext-ref'] = $orderNumber;
    $buyLinkParams['customer-ext-ref'] = $client['email'];
    $buyLinkParams['currency'] = strtolower($currencyCode);
    $buyLinkParams['test'] = $testMode === 'on' ? 1 : 0;
    $buyLinkParams['merchant'] = $accountId;
    $buyLinkParams['dynamic'] = 1;

    $buyLinkParams['signature'] = TwocheckoutHelper::getSignature($accountId, $secretWord, $buyLinkParams);
    if ($buyLinkParams['signature']) {
        $htmlOutput = '<form method="get" action="https://secure.2checkout.com/checkout/buy">';
        foreach ($buyLinkParams as $input_name => $value) {
            $htmlOutput .= '<input type="hidden" name="' . $input_name . '" value="' . $value . '" >';
        }
        $htmlOutput .= '<input type="submit" class="btn btn-success btn-sm" value="' . $langPayNow . '" />';
        $htmlOutput .= '</form>';

        return $htmlOutput;
    } else {
        return "<div><p>Ups, something went wrong! Please try again.</p></div>";
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
function twocheckoutconvertplus_refund($params)
{
    return TwocheckoutHelper::refund($params, $_POST);
}

/**
 * @param $params
 *
 * @return array
 * @throws \Exception
 */
function twocheckoutconvertplus_cancelSubscription($params)
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
