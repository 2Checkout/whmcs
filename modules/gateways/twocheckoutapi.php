<?php

use WHMCS\Billing\Invoice;
use WHMCS\Database\Capsule;


require_once realpath(dirname(__FILE__)) . "/twocheckout/lib/TwocheckoutHelper.php";
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
    $default_style = "{
                    'margin': '0',
                    'fontFamily': 'Helvetica, sans-serif',
                    'fontSize': '1rem',
                    'fontWeight': '400',
                    'lineHeight': '1.5',
                    'color': '#212529',
                    'textAlign': 'left',
                    'backgroundColor': 'FFFFFF',
                    '*': {
                        'boxSizing': 'border-box'
                    },
                    '.no-gutters': {
                        'marginRight': 0,
                        'marginLeft': 0
                    },
                    '.row': {
                        'display': 'flex',
                        'flexWrap': 'wrap'
                    },
                    '.col': {
                        'flexBasis': '0',
                        'flexGrow': '1',
                        'maxWidth': '100%',
                        'padding': '0',
                        'position': 'relative',
                        'width': '100%'
                    },
                    'div': {
                        'display': 'block'
                    },
                    '.field-container': {
                        'paddingBottom': '14px'
                    },
                    '.field-wrapper': {
                        'paddingRight': '25px'
                    },
                    '.input-wrapper': {
                        'position': 'relative'
                    },
                    'label': {
                        'display': 'inline-block',
                        'marginBottom': '9px',
                        'color': '#313131',
                        'fontSize': '14px',
                        'fontWeight': '300',
                        'lineHeight': '17px'
                    },
                    'input': {
                        'overflow': 'visible',
                        'margin': 0,
                        'fontFamily': 'inherit',
                        'display': 'block',
                        'width': '100%',
                        'height': '42px',
                        'padding': '10px 12px',
                        'fontSize': '18px',
                        'fontWeight': '400',
                        'lineHeight': '22px',
                        'color': '#313131',
                        'backgroundColor': '#FFF',
                        'backgroundClip': 'padding-box',
                        'border': '1px solid #CBCBCB',
                        'borderRadius': '3px',
                        'transition': 'border-color .15s ease-in-out,box-shadow .15s ease-in-out',
                        'outline': 0
                    },
                    'input:focus': {
                        'border': '1px solid #5D5D5D',
                        'backgroundColor': '#FFFDF2'
                    },
                    '.is-error input': {
                        'border': '1px solid #D9534F'
                    },
                    '.is-error input:focus': {
                        'backgroundColor': '#D9534F0B'
                    },
                    '.is-valid input': {
                        'border': '1px solid #1BB43F'
                    },
                    '.is-valid input:focus': {
                        'backgroundColor': '#1BB43F0B'
                    },
                    '.validation-message': {
                        'color': '#D9534F',
                        'fontSize': '10px',
                        'fontStyle': 'italic',
                        'marginTop': '6px',
                        'marginBottom': '-5px',
                        'display': 'block',
                        'lineHeight': '1'
                    },
                    '.card-expiration-date': {
                        'paddingRight': '.5rem'
                    },
                    '.is-empty input': {
                        'color': '#EBEBEB'
                    },
                    '.lock-icon': {
                        'top': 'calc(50% - 7px)',
                        'right': '10px'
                    },
                    '.valid-icon': {
                        'top': 'calc(50% - 8px)',
                        'right': '-25px'
                    },
                    '.error-icon': {
                        'top': 'calc(50% - 8px)',
                        'right': '-25px'
                    },
                    '.card-icon': {
                        'top': 'calc(50% - 10px)',
                        'left': '10px',
                        'display': 'none'
                    },
                    '.is-empty .card-icon': {
                        'display': 'block'
                    },
                    '.is-focused .card-icon': {
                        'display': 'none'
                    },
                    '.card-type-icon': {
                        'right': '30px',
                        'display': 'block'
                    },
                    '.card-type-icon.visa': {
                        'top': 'calc(50% - 14px)'
                    },
                    '.card-type-icon.mastercard': {
                        'top': 'calc(50% - 14.5px)'
                    },
                    '.card-type-icon.amex': {
                        'top': 'calc(50% - 14px)'
                    },
                    '.card-type-icon.discover': {
                        'top': 'calc(50% - 14px)'
                    },
                    '.card-type-icon.jcb': {
                        'top': 'calc(50% - 14px)'
                    },
                    '.card-type-icon.dankort': {
                        'top': 'calc(50% - 14px)'
                    },
                    '.card-type-icon.cartebleue': {
                        'top': 'calc(50% - 14px)'
                    },
                    '.card-type-icon.diners': {
                        'top': 'calc(50% - 14px)'
                    },
                    '.card-type-icon.elo': {
                        'top': 'calc(50% - 14px)'
                    }
                }";

    $extraFields = [
        'defaultStyle' => [
            'FriendlyName' => 'Use default style',
            'Type' => 'yesno',
            'Description' => 'Yes, I like the default style',
        ],
        'customTwoPayStyle' => [
            'FriendlyName' => 'Custom style',
            'Type' => 'textarea',
            'Rows' => '5',
            'Cols' => '30',
            'Default' => $default_style,
            'Description' => '<i style="color: #e35d5d"><b>IMPORTANT! </b><br /> This is the styling object that styles your form.
                     Do not remove or add new classes. You can modify the existing ones. Use
                      double quotes for all keys and values!  <br /> VALID JSON FORMAT REQUIRED (validate 
                      json before save here: <a href="https://jsonlint.com/" target="_blank">https://jsonlint.com/</a>) </i>. <br >
                      Also you can find more about styling your form <a href="https://knowledgecenter.2checkout.com/API-Integration/2Pay.js-payments-solution/2Pay.js-Payments-Solution-Integration-Guide/How_to_customize_and_style_the_2Pay.js_payment_form"
                       target="_blank">here</a>!',
        ]
    ];
    return TwocheckoutHelper::config('2Checkout API Gateway', \App::getSystemURL() . 'modules/gateways/callback/twocheckout-ipn.php?tco_type=api', $extraFields);

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
        WHMCS\Session::set("remoteStorageToken", (string)App::getFromRequest("remoteStorageToken"));
    }
    return "";
}

function twocheckoutapi_credit_card_input(array $params = array())
{
    $assetHelper = DI::make("asset");
    $jsUrl = $assetHelper->getWebRoot() . "/modules/gateways/twocheckout/twocheckoutapi.js";

    return "<script type=\"text/javascript\">\n  var customTwoPayStyle = " . $params["customTwoPayStyle"] . ";" . "     var defaultStyle = '" . $params["defaultStyle"] . "';" . "  var accountId = '" . $params["accountId"] . "';" . "\n</script>\n<script type=\"text/javascript\" src=\"" . $jsUrl . "\"></script>";
}

function twocheckoutapi_storeremote(array $params = array())
{
    $token = WHMCS\Session::getAndDelete("remoteStorageToken");
    if (!$token && App::isInRequest("remoteStorageToken")) {
        $token = (string)App::getFromRequest("remoteStorageToken");
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
    $twocheckoutConfig = [
        "accountId" => $params['accountId'],
        "secretKey" => $params['secretKey']
    ];

    $itemsArray = [];
    $currency = $params['currency'];
    $invoice = Invoice::find($params['invoiceid']);
    $products = $invoice->getBillingValues();
    $coupons = $invoice->items->toArray();
    $overdue = $products['overdue'];
    unset($products['overdue']);
    $recurring = false;

    foreach ($products as $item) {
        $lineItem = [];
        if (array_key_exists('firstPaymentAmount', $item)) {
            $lineItemAmount = $item['firstPaymentAmount'];
        } else {
            $lineItemAmount = $item['amount'];
        }
        $lineItem["Code"] = null;
        $lineItem["Quantity"] = "1";
        $lineItem["IsDynamic"] = true;
        $lineItem["Tangible"] = false;

        if ($lineItemAmount >= 0) {
            $lineItem["PurchaseType"] = "PRODUCT";
            $lineItem["Name"] = $item['description'];
            $lineItem["Price"] = [
                "Amount" => abs($lineItemAmount),
                "Type" => "CUSTOM",
                "Currency" => $currency
            ];
            $lineItem["ExternalReference"] = $item['itemId'];

            if (!$overdue && $item['recurringCyclePeriod'] && $item['recurringCyclePeriod'] > 0) {
                $recurringDetails = [];
                $recurringDetails["CycleLength"] = $item['recurringCyclePeriod'];
                $recurringDetails["CycleUnit"] = TwocheckoutHelper::mapRecurringUnit($item['recurringCycleUnits']);
                $recurringDetails["CycleAmount"] = abs($lineItemAmount);
                $recurringDetails["ContractLength"] = 0;
                $recurringDetails["ContractUnit"] = "FOREVER";
                $lineItem['RecurringOptions'] = $recurringDetails;

                $recurring = true;
            }

            $serviceId = (int)preg_replace('/\D/', '', $item['itemId']);
            //work around for recurring products with nonrecurring promotions
            foreach ($coupons as $product) {
                if ((isset($product['relid']) && $product['relid'] === $serviceId) &&
                    (isset($product['service']) && $product['service']) && $product['amount'] > 0) {
                    $promotion = Capsule::table('tblpromotions')->find($product['service']['promoid']);
                    if ($promotion && $promotion->recurring === 0 && $product) {
                        $lineItem["Price"]["Amount"] = abs($product['service']['firstpaymentamount']);
                    }
                }
            }
        } else {
            // We have a discount and need to apply it as a coupon
            $lineItem["PurchaseType"] = "COUPON";
            $lineItem["Name"] = $item['description'];
            $lineItem["Price"] = array(
                "Amount" => abs($item['amount']),
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
        "Vendor3DSReturnURL" => $params['systemurl'] . "modules/gateways/callback/twocheckout-ipn.php?tco_type=api",
        "Vendor3DSCancelURL" => $params['systemurl'] . "modules/gateways/callback/twocheckout-ipn.php?tco_type=api",
    );

    $paymentMethodDetails['RecurringEnabled'] = $recurring;

    // Order Details
    $orderDetails = array(
        "Currency" => $currency,
        "Language" => $params['en'],
        "Country" => $params['clientdetails']['country'],
        "ExternalReference" => $params['invoiceid'],
        "Source" => 'WHMCS_' . $params['whmcsVersion'],
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
        $responseData = TwocheckoutHelper::callAPI("POST", "orders/", $twocheckoutConfig, $orderDetails);

        if (isset($responseData['Status'])) {
            if (isset($responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']) &&
                isset($responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']['Href']) &&
                !empty($responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']['Href'])) {
                header("Location: " . $responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']['Href'] . '?avng8apitoken=' . $responseData['PaymentDetails']['PaymentMethod']['Authorize3DS']['Params']['avng8apitoken']);
                exit;
            } else {
                if ($responseData['Status'] == 'AUTHRECEIVED') {
                    // check if we want to mark the invoice as pending or paid
                    if (isset($params['skipFraud']) and !empty($params['skipFraud'])) {

                        $returnData = [
                            'status' => 'success',
                            'rawdata' => $responseData,
                            'transid' => $responseData['RefNo']
                        ];
                    } else {
                        $url = \App::getSystemURL() . "viewinvoice.php?id=" . $params['invoiceid'] . "&pendingreview=true";
                        header("Location:" . $url);
                        exit;
                    }

                    $returnData = [
                        'status' => 'success',
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
    return TwocheckoutHelper::refund($params, $_POST);
}

function cmpPrices($a, $b)
{
    if ($a['Price']['GrossPrice'] < $b['Price']['GrossPrice']) {
        return 1;
    } else {
        if ($a['Price']['GrossPrice'] > $b['Price']['GrossPrice']) {
            return -1;
        } else {
            return 0;
        }
    }
}

function mapRecurringUnit($unit)
{
    $recurringUnits = [
        "Days" => "DAY",
        "Weeks" => "WEEK",
        "Months" => "MONTH",
        "Years" => "YEAR"
    ];

    return $recurringUnits[$unit];
}
