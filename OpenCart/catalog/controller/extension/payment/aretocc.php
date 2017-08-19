<?php

require_once DIR_SYSTEM . 'areto-sdk/vendor/php-credit-card-validator/src/CreditCard.php';
require_once DIR_SYSTEM . 'areto-sdk/Areto.php';

class ControllerExtensionPaymentAretocc extends Controller
{
    protected $_module_name = 'aretocc';

    protected static $_api;

    /**
     * Index Action
     */
    public function index()
    {
        $this->language->load('extension/payment/aretocc');

        $data['text_title'] = $this->language->get('text_title');
        $data['text_description'] = $this->language->get('text_description');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['continue'] = $this->url->link('checkout/success');

        $data['action'] = $this->url->link('extension/payment/aretocc/confirm');

        return $this->load->view('extension/payment/aretocc', $data);
    }

    /**
     * Validate Action
     */
    public function confirm()
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/aretocc');

        $order_id = $this->session->data['order_id'];

        // Load Order
        $order = $this->model_checkout_order->getOrder($order_id);

        // Get Credit Card Fields
        $cardNumber = preg_replace('/\s+/', '', $this->request->post['cardNumber']);
        $cardExpiry = explode('/', $this->request->post['cardExpiry']);
        $cardCVC = $this->request->post['cardCVC'];

        // Get DOB
        $date_of_birth = $this->request->post['date_of_birth'];
        $date_of_birth = date('Y-m-d', strtotime($date_of_birth));

        // Detect credit card type
        $card = Inacho\CreditCard::validCreditCard($cardNumber);
        if (!$card['valid']) {
            $this->session->data['areto_error'] = 'Invalid CC number';
            $this->response->redirect($this->url->link('extension/payment/aretocc/error', '', 'SSL'));
            return;
        }

        $types = array(
            'visaelectron' => 'VisaElectron',
            'maestro' => 'MAES',
            'visa' => 'VISA',
            'mastercard' => 'MC',
            'amex' => 'AMEX',
            'dinersclub' => 'DINER',
            'discover' => 'DISC',
            'unionpay' => 'CUP',
            'jcb' => 'JCB',
        );
        $type = isset($types[$card['type']]) ? $types[$card['type']] : strtoupper($card['type']);

        $validate_url = $this->url->link('extension/payment/aretocc/validate', '', 'SSL');

        // Short URL to workaround Areto 3DS (query params are lost)
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, 'http://tinyurl.com/api-create.php?url=' . $validate_url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
        $validate_url = curl_exec($ch);
        curl_close($ch);

	$products = $this->cart->getProducts();
        $items_array = array();
        foreach ($products as $item) {
            $items_array[] =  urlencode($item['model']).','.urlencode($item['quantity']).','.urlencode($item['name']);
        }
	    
        // Sale Request
        $data = array(
            'order_id' => $order['order_id'],
            'amount' => number_format($order['total'], 2),
	    'items' => implode('|', $items_array),
            'currency_code' => $order['currency_code'],
            'CVC' => $cardCVC,
            'expiry_month' => $cardExpiry[0],
            'expiry_year' => strlen($cardExpiry[1]) < 4 ? 2000 + $cardExpiry[1] : $cardExpiry[1],
            'name' => $order['payment_firstname'],
            'surname' => $order['payment_lastname'],
            'number' => $cardNumber,
            'type' => $type,
            'address' => trim($order['payment_address_1'] . ' ' . $order['payment_address_2']),
            'client_city' => $order['payment_city'],
            'client_country_code' => $order['payment_iso_code_2'],
            'client_zip' => $order['payment_postcode'],
            'client_state' => $order['payment_zone'],
            'client_email' => $order['email'],
            'client_external_identifier' => '',
            'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'client_forward_IP' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
            'client_DOB' => $date_of_birth,
            'client_phone' => $order['telephone'],
            'token' => '',
            'create_token' => '0',
            'return_url' => $validate_url
        );

        $result = $this->getApi()->sale_request($data);
        if (!is_array($result) || !isset($result['Result'])) {
            //throw new Exception('Unable process request: invalid response');
            $message = 'Unable process request: invalid response';
            $this->session->data['areto_error'] = $message;
            $this->response->redirect($this->url->link('extension/payment/aretocc/error', '', 'SSL'));
            return;
        }

        switch ((int)$result['Result']['Code']) {
            case 0:
                // Payment is failed
                $message = sprintf('Payment failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']);
                $this->session->data['areto_error'] = $message;
                $this->response->redirect($this->url->link('extension/payment/aretocc/error', '', 'SSL'));
                break;
            case 1:
                // Payment is success
                //echo sprintf('Payment success: %s. Internal OrderID: %s', $result['Result']['Description'], $result['Body']['InternalOrderID']);
                $this->addTransaction($order_id, $result['Body']['InternalOrderID']);
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('aretocc_completed_status_id'), '', true);
                $this->response->redirect($this->url->link('checkout/success', '', 'SSL'));
                break;
            case 4:
                // Payment require 3D-Secure
                // Save InternalOrderID value
                $_SESSION['ARETO_ORDER_ID'] = $result['Body']['InternalOrderID'];

                // 3D-Secure with params
                if (count($result['Redirect']) > 0) {
                    $url = urldecode($result['Redirect']['RedirectLink']);
                    $method = !empty($result['Redirect']['Method']) ? $result['Redirect']['Method'] : 'POST';
                    $params = $result['Redirect']['Parameters'];

                    echo '<br /><strong>Redirect to payment gateway...</strong>';
                    echo sprintf('<form id="areto_checkout" action="%s" method="%s">', $url, $method);
                    foreach ($params as $key => $value) {
                        echo sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value);
                    }
                    echo '</form>';
                    echo '<script>document.getElementById(\'areto_checkout\').submit();</script>';
                }
                exit();
            default:
                $message = sprintf('Error: %s. Code: %s', $result['Result']['Description'], $result['Result']['Code']);
                $this->session->data['areto_error'] = $message;
                $this->response->redirect($this->url->link('extension/payment/aretocc/error', '', 'SSL'));
                break;
        }
    }

    /**
     * Validate Action
     */
    public function validate()
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/aretocc');

        $order_id = $this->session->data['order_id'];

        // Load Order
        $order = $this->model_checkout_order->getOrder($order_id);

        if (!isset($_SESSION['ARETO_ORDER_ID'])) {
            die('Wrong Internal Order ID.');
        }

        // Get InternalOrderID value
        $internal_order_id = $_SESSION['ARETO_ORDER_ID'];
        unset($_SESSION['ARETO_ORDER_ID']);

	    try {
		    // Wait for order status != 4
		    set_time_limit(0);
		    $times = 0;
		    do {
			    $times++;
			    if ($times > 6) {
				    throw new Exception('Status check timeout');
			    }
			    sleep(10);
			    $result = $this->getApi()->status_request($internal_order_id);

			    // Check request is failed
			    if ((int)$result['Result']['Code'] !== 1) {
				    throw new Exception(sprintf('Status Check is failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']));
			    }

			    $order_status = (int)$result['Body']['OrderStatus'];
		    } while ($order_status === 4);

		    switch ((int)$result['Body']['OrderStatus']) {
			    case 0:
				    // Payment has been declined
				    $message = 'Payment has been declined';
				    $this->session->data['areto_error'] = $message;
				    $this->response->redirect($this->url->link('extension/payment/aretocc/error', '', 'SSL'));
				    break;
			    case 1:
				    // Payment is success
				    // echo sprintf('Payment success: %s. InternalOrderID: %s', $result['Body']['OrderDescription'], $result['Body']['InternalOrderID']);
				    $this->addTransaction($order_id, $result['Body']['InternalOrderID']);
				    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('aretocc_completed_status_id'), '', true);
				    $this->response->redirect($this->url->link('checkout/success', '', 'SSL'));
				    break;
			    default:
				    $message = 'Unknown order status';
				    $this->session->data['areto_error'] = $message;
				    $this->response->redirect($this->url->link('extension/payment/aretocc/error', '', 'SSL'));
				    break;
		    }
	    } catch (Exception $e) {
		    $this->session->data['areto_error'] = $e->getMessage();
		    $this->response->redirect($this->url->link('extension/payment/aretocc/error', '', 'SSL'));
	    }
    }

    /**
     * Error Action
     */
    public function error()
    {
        $this->load->language('extension/payment/aretocc');

        $data['heading_title'] = $this->language->get('text_error');
        if (!empty($this->session->data['areto_error'])) {
            $data['description'] = $this->session->data['areto_error'];
        } else {
            $data['description'] = $this->language->get('text_error');
        }
        $data['link_text'] = $this->language->get('link_text_try_again');
        $data['link'] = $this->url->link('checkout/checkout', '', 'SSL');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/areto_error', $data));
    }

    /**
     * Get Areto Handler
     * @return Areto
     */
    protected function getApi()
    {
        if (is_null(self::$_api)) {
            $api_id = $this->config->get('aretocc_api_id');
            $api_session = $this->config->get('aretocc_api_session');
            self::$_api = new Areto;
            self::$_api->setEnvironment($api_id, $api_session);
        }

        return self::$_api;
    }

    /**
     * Add Payment Transaction
     * @param $order_id
     * @param $internal_order_id
     * @return bool
     */
    protected function addTransaction($order_id, $internal_order_id)
    {
        $query = sprintf('INSERT INTO `' . DB_PREFIX . 'aretocc_orders` (order_id, internal_order_id) VALUES (%d, %d);',
            $this->db->escape((int)$order_id),
            $this->db->escape((int)$internal_order_id)
        );
        try {
            return $this->db->query($query);
        } catch(Exception $e) {
            return false;
        }
    }
}
