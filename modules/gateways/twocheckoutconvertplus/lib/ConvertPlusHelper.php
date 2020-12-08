<?php

/**
 * Class ConvertPlusHelper
 */
class ConvertPlusHelper
{

    const SIGNATURE_URL = "https://secure.2checkout.com/checkout/api/encrypt/generate/signature";

    /**
     * @param $sellerId
     * @param $secretWord
     * @param $payload
     * @return mixed
     */
    public function getSignature($sellerId, $secretWord, $payload)
    {
        $jwtToken = $this->generateJWT($sellerId, $secretWord);

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
            logActivity('Error when trying to place order. Err:'.$err);
        }

        $response = json_decode($response, true);
        if (JSON_ERROR_NONE !== json_last_error() || !isset($response['signature']) || empty($response['signature'])) {
            logActivity('Unable to get proper response from signature generation API: '.json_last_error());
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

}
