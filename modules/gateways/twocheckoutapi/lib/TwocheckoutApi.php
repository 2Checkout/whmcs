<?php

class TwocheckoutApi
{
    public static function callAPI($method = "POST", $action = "", $config = array(), $payload = array())
    {   
        $date = gmdate('Y-m-d H:i:s');
        $accept = 'application/json';
        $code = $config['accountId'];
        $key = $config['secretKey'];
        $hash = hash_hmac('md5', strlen($code) . $code . strlen($date) . $date, $key);
        $headers = [
            'X-Avangate-Authentication: '.'code="' . $code . '" date="' . $date . '" hash="' . $hash . '"',
            'Accept: '. $accept,
            'Content-Type: application/json',
            'Expect:'
        ];
        
        $url = "https://api.2checkout.com/rest/6.0/" . $action;
        $ch = curl_init($url);

        $jsonPayload = json_encode($payload);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            $headers[] = 'Content-Length:    '.strlen($jsonPayload);
        }
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $resp = curl_exec($ch);
        
        $curl_info = curl_getinfo($ch);
        $result = json_decode(substr($resp, $curl_info['header_size']), true);

        if (curl_errno($ch) > 0) 
        { 
            throw new \Exception(curl_error($ch));
        } 
        curl_close($ch);
        if ($result === FALSE) {
            throw new \Exception("cURL call failed");
        } else {
            return $result;
        }
    }
}