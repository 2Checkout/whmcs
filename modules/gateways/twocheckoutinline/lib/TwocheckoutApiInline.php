<?php

class TwocheckoutApiInline
{

    const SIGNATURE_URL = "https://secure.2checkout.com/checkout/api/encrypt/generate/signature";

    /**
     * @param $sellerId
     * @param $secretWord
     * @param $payload
     * @return mixed
     */
    public static function getInlineSignature($sellerId, $secretWord, $payload)
    {
        $jwtToken = self::generateJWT($sellerId, $secretWord);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => self::SIGNATURE_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'cache-control: no-cache',
                'merchant-token: ' . $jwtToken,
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            logActivity('Error when trying to place order');
        }

        $response = json_decode($response, true);
        if (JSON_ERROR_NONE !== json_last_error() || !isset($response['signature'])) {
            logActivity('Unable to get proper response from signature generation API');
        }

        return $response['signature'];
    }

    /**
     * @param $sellerId
     * @param $secretWord
     * @return string
     */
    private function generateJWT($sellerId, $secretWord)
    {
        $header = self::encode(json_encode(['alg' => 'HS512', 'typ' => 'JWT']));
        $payload = self::encode(json_encode(['sub' => $sellerId, 'iat' => time(), 'exp' => time() + 3600]));
        $signature = self::encode(hash_hmac('sha512', "$header.$payload", $secretWord, true));

        return implode('.', [$header, $payload, $signature]);
    }

    /**
     * @param $data
     *
     * @return string|string[]
     */
    private function encode($data)
    {
        return str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
    }

    public static function callAPI($method = "POST", $action = "", $config = [], $payload = [])
    {
        $date = gmdate('Y-m-d H:i:s');
        $accept = 'application/json';
        $code = $config['accountId'];
        $key = $config['secretKey'];
        $hash = hash_hmac('md5', strlen($code) . $code . strlen($date) . $date, $key);
        $headers = [
            'X-Avangate-Authentication: ' . 'code="' . $code . '" date="' . $date . '" hash="' . $hash . '"',
            'Accept: ' . $accept,
            'Content-Type: application/json',
            'Expect:'
        ];
        $url = "https://api.2checkout.com/rest/6.0/" . $action;
        $ch = curl_init($url);

        $jsonPayload = json_encode($payload);

        logActivity($jsonPayload, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            $headers[] = 'Content-Length:    ' . strlen($jsonPayload);
        }
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $resp = curl_exec($ch);

        $curl_info = curl_getinfo($ch);
        $result = json_decode(substr($resp, $curl_info['header_size']), true);

        logActivity(json_encode($result), 0);
        if (curl_errno($ch) > 0) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);
        if ($result === false) {
            throw new \Exception("cURL call failed");
        } else {
            return $result;
        }
    }
}
