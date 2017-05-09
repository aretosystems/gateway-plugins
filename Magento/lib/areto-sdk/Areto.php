<?php

/**
 * Areto API
 *
 * @version 1.0.0
 * @author  Oleg Iskusnyh
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

class Areto
{
    public $api_id = '';
    public $api_session = '';

    /**
     * Set Environment
     * @param $id
     * @param $session
     */
    public function setEnvironment($id, $session)
    {
        $this->api_id = $id;
        $this->api_session = $session;
    }

    /**
     * Direct Sale Request
     * @param array $data
     *
     * @return array|mixed|object
     * @throws Exception
     */
    public function sale_request(array $data)
    {
        // Default options
        $default = array(
            'order_id' => '',
            'amount' => 0,
            'currency_code' => '',
            'CVC' => '',
            'expiry_month' => '',
            'expiry_year' => '',
            'name' => '',
            'surname' => '',
            'number' => '',
            'type' => '',
            'address' => '',
            'client_city' => '',
            'client_country_code' => '',
            'client_zip' => '',
            'client_state' => '',
            'client_email' => '',
            'client_external_identifier' => '',
            'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'client_forward_IP' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
            'client_DOB' => '',
            'client_phone' => '',
            'token' => '',
            'create_token' => '0',
            'return_url' => ''
        );

        // Prepare options
        $data = array_merge($default, $data);

        // Prepare params
        $params = array(
            'Id' => $this->api_id,
            'Session' => $this->api_session,
            'OrderId' => $data['order_id'],
            'Amount' => $data['amount'],
            'CurrencyCode' => $data['currency_code'],
            'CCVC' => $data['CVC'],
            'CCExpiryMonth' => $data['expiry_month'],
            'CCExpiryYear' => $data['expiry_year'],
            'CCName' => $data['name'],
            'CCSurname' => $data['surname'],
            'CCNumber' => $data['number'],
            'CCType' => $data['type'],
            'CCAddress' => $data['address'],
            'ClientCity' => $data['client_city'],
            'ClientCountryCode' => $data['client_country_code'],
            'ClientZip' => (string) $data['client_zip'],
            'ClientState' => $data['client_state'],
            'ClientEmail' => $data['client_email'],
            'ClientExternalIdentifier' => $data['client_external_identifier'],
            'ClientIP' => $data['client_ip'],
            'ClientForwardIP' => $data['client_forward_IP'],
            'ClientDOB' => $data['client_DOB'], //yyyy-mm-dd
            'ClientPhone' => $data['client_phone'],
            'CCToken' => $data['token'],
            'CreateToken' => $data['create_token'],
            'ReturnUrl' => $data['return_url'],
        );

        //$fields = http_build_query($data, '', '&');
        $fields = '';
        foreach ($params as $key => $value) {
            $fields .= "{$key}={$value}&";
        }
        $fields = rtrim($fields, '&');

        // Do request
        $url = 'https://pay.aretosystems.com/api/sale/v1';

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
            CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json'
            )
        ));
        $response = curl_exec($ch);
        curl_close($ch);

        // Decode JSON
        $result = @json_decode($response, true);
        return $result;
    }

    /**
     * Status Request
     * @param $internal_order_id
     *
     * @return array|mixed|object
     * @throws Exception
     */
    public function status_request($internal_order_id)
    {
        // Prepare params
        $params = array(
            'Id' => $this->api_id,
            'Session' => $this->api_session,
            'InternalOrderID' => $internal_order_id
        );

        //$fields = http_build_query($data, '', '&');
        $fields = '';
        foreach ($params as $key => $value) {
            $fields .= "{$key}={$value}&";
        }
        $fields = rtrim($fields, '&');

        // Do request
        $url = 'https://pay.aretosystems.com/api/status/v1/?' . $fields ;

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json'
            )
        ));
        $response = curl_exec($ch);
        curl_close($ch);

        // Decode JSON
        $result = @json_decode($response, true);
        return $result;
    }


    /**
     * Refund request
     * @param int $internal_order_id
     * @param float $amount
     * @param string $reason
     * @return bool
     * @throws Exception
     */
    public function refund_request($internal_order_id, $amount, $reason = '')
    {
        // Prepare params
        $params = array(
            'Id' => $this->api_id,
            'Session' => $this->api_session,
            'InternalOrderId' => $internal_order_id,
            'Reason' => $reason,
            'Amount' => $amount,
        );

        // Do request
        $url = 'https://pay.aretosystems.com/pay-refund.ashx';

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params
        ));
        $response = curl_exec($ch);
        curl_close($ch);

        // Parse response
        $result = self::parse_xml($response);

        if ((int)$result['c-code'] === 6) {
            return true;
        } else {
            throw new Exception('Unable process request: ' . $result['c-message']);
        }
    }

    /**
     * Parse Request
     * @param $xml_body
     *
     * @return array|bool
     */
    public static function parse_xml($xml_body)
    {
        // Load XML
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $status = @$doc->loadXML($xml_body);
        if ($status === false) {
            return false;
        }

        // Get Error section
        $result = array();
        $items = $doc->getElementsByTagName('c-result')->item(0)->getElementsByTagName('c-error')->item(0)->getElementsByTagName('*');
        foreach ($items as $item) {
            $key = $item->nodeName;
            $value = $item->nodeValue;
            $result[$key] = $value;
        }

        // Get Container section
        $items = $doc->getElementsByTagName('c-result')->item(0)->getElementsByTagName('c-container')->item(0)->getElementsByTagName('*');
        foreach ($items as $item) {
            $key = $item->nodeName;
            $value = $item->nodeValue;
            $result[$key] = $value;
        }
        return $result;
    }
}