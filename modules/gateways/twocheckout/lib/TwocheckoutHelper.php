<?php

require_once "TwocheckoutApi.php";

class TwocheckoutHelper
{

    /**
     * make sure we check the currency and format it to same as order
     * @param $price
     * @param $params
     * @return string
     */
    private function getConvertedPrice($price, $params)
    {
        $exchange_rate = (float)$params['FxRate'] > 0 ? (float)$params['FxRate'] : 1;
        $fx_commission = ((float)$params['FxMarkup'] > 0) ? 100 / (100 - (float)$params['FxMarkup']) : 1;
        $amount = ((float)$price) / $exchange_rate / $fx_commission;

        return number_format($amount, 2);
    }


    /**
     * @param $unit
     * @return string
     */
    public static function mapRecurringUnit($unit)
    {
        $recurringUnits = [
            "Days" => "DAY",
            "Weeks" => "WEEK",
            "Months" => "MONTH",
            "Years" => "YEAR"
        ];

        return $recurringUnits[$unit];
    }

    /**
     * @param $params
     * @param $post
     * @return array
     * @throws Exception
     */
    public static function refund($params, $post = [])
    {
        // Gateway Configuration Parameters
        $twocheckoutConfig = [
            "accountId" => $params['accountId'],
            "secretKey" => $params['secretKey']
        ];
        $refundReason = (isset($post['reason']) && !empty($post['reason'])) ? $post['reason'] : 'Cancellation';
        $refundComment = (isset($post['comment']) && !empty($post['comment'])) ? $post['comment'] : '';


        $orderData = TwocheckoutApi::callAPI("GET", "orders/{$params['transid']}/", $twocheckoutConfig);
        $amount = self::getConvertedPrice($params['amount'], $orderData);
        if ($amount == $orderData['GrossPrice']) {
            // Refund Details
            $refundDetails = [
                "amount" => $amount,
                "comment" => $refundComment,
                "reason" => $refundReason
            ];
        } else {
            $lineItems = $orderData["Items"];
            usort($lineItems, "cmpPrices");
            $paymentAmount = self::getConvertedPrice($lineItems[0]['Price']['GrossPrice'], $orderData);
            if ($paymentAmount >= $amount) {
                // Refund Item Details
                $itemsArray[] = [
                    "Quantity" => "1",
                    "LineItemReference" => $lineItems[0]["LineItemReference"],
                    "Amount" => $amount
                ];

                // Refund Details
                $refundDetails = [
                    "amount" => $params['amount'],
                    "comment" => $refundComment,
                    "reason" => $refundReason,
                    "items" => $itemsArray
                ];
            } else {
                return [
                    'status' => 'error',
                    'rawdata' => 'Partial refund amount cannot exceed the highest priced item. Please login to your 2Checkout admin to issue the partial refund manually.',
                    'transid' => $params['transid'],
                ];
            }
        }

        try {
            //refund amount
            $responseData = TwocheckoutApi::callAPI("POST", "orders/{$params['transid']}/refund/", $twocheckoutConfig, $refundDetails);
            //cancel all subscription
            foreach ($orderData['Items'] as $item) {
                if (isset($item['ProductDetails']['Subscriptions']) && count($item['ProductDetails']['Subscriptions'])) {
                    $subscriptionReference = $item['ProductDetails']['Subscriptions'][0]['SubscriptionReference'];
                    TwocheckoutApi::callAPI('DELETE', "subscriptions/{$subscriptionReference}/", $twocheckoutConfig);
                }

            }
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

    /**
     * @param $sellerId
     * @param $secretWord
     * @param $payload
     * @return mixed
     */
    public static function getSignature($sellerId, $secretWord, $payload)
    {
        return TwocheckoutApi::getSignature($sellerId, $secretWord, $payload);
    }

    /**
     * @param string $method
     * @param string $action
     * @param array $config
     * @param array $payload
     * @return mixed
     * @throws Exception
     */
    public static function callAPI($method = "POST", $action = "", $config = [], $payload = [])
    {
        return TwocheckoutApi::callAPI($method, $action, $config, $payload);
    }

    /**
     *  create the configuration fields for gateway
     * @param $name
     * @param $ipn
     * @param array $extraFields
     * @return array
     */
    public function config($name, $ipn, $extraFields = [])
    {
        $fields = [
            'FriendlyName' => [
                'Type' => 'System',
                'Value' => $name,
            ],
            'accountId' => [
                'FriendlyName' => 'Merchant Code',
                'Type' => 'text',
                'Size' => '30',
                'Default' => '',
                'Description' => 'Enter your Merchant Code here',
            ],
            'secretWord' => [
                'FriendlyName' => 'Secret Word',
                'Type' => 'password',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Enter secret word here',
            ],
            'secretKey' => [
                'FriendlyName' => 'Secret Key',
                'Type' => 'password',
                'Size' => '50',
                'Default' => '',
                'Description' => 'Enter secret key here',
            ],
            'testMode' => [
                'FriendlyName' => 'Test Mode',
                'Type' => 'yesno',
                'Description' => 'Tick to enable test mode',
            ],
            'skipFraud' => [
                'FriendlyName' => 'Skip 2CO Fraud Check',
                'Type' => 'yesno',
                'Description' => 'Tick to mark invoices as paid without waiting for 2Checkout fraud review.',
            ],
            'inpUrl_inline' => [
                'FriendlyName' => 'IPN URL',
                'Type' => 'text',
                'Size' => '65',
                'ReadOnly' => true,
                'Default' => $ipn,
                'Description' => 'Copy this link to your 2checkout account in the IPN section (check the documentation)!*',
            ],
            'refundReasons' => [
                'FriendlyName' => 'Reasons for Refunds',
                'Type' => 'textarea',
                'Rows' => '10',
                'Cols' => '30',
                'Description' => 'Enter the reasons for refunds that you have <a target="_blank" href="https://knowledgecenter.2checkout.com/Documentation/27Refunds_and_chargebacks/Refunds#Adding_custom_refund_reasons">setup in your 2Checkout account</a>.',
            ],
            'inlineTemplate' => [
                'FriendlyName' => 'Template',
                'Type' => 'radio',
                'Options' => 'One step inline,Multi step inline',
                'Default' => 'One step inline',
                'Description' => 'Choose between a multi-step or a one-step inline checkout.',
            ],
        ];
        return array_merge($fields, $extraFields);
    }

    /**
     * sets the platform version but only the major and minor iteration, without the release version
     * @param $version
     * @return string
     */
    public static function getFormattedVersion($version)
    {
        $pieces = explode('.', $version);
        return 'WHMCS_' . $pieces[0] . '_' . $pieces[1];
    }
}