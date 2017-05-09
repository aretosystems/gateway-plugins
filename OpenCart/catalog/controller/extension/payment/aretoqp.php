<?php

require_once DIR_SYSTEM . 'areto-sdk/Areto.php';

class ControllerExtensionPaymentAretoqp extends Controller
{
    protected $_module_name = 'aretoqpp';

    protected static $_api;

    /**
     * Index Action
     */
    public function index()
    {
        $this->language->load('extension/payment/aretoqp');

        $data['text_title'] = $this->language->get('text_title');
        $data['text_description'] = $this->language->get('text_description');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['continue'] = $this->url->link('checkout/success');

        $data['action'] = $this->url->link('extension/payment/aretoqp/confirm');

        return $this->load->view('extension/payment/aretoqp', $data);
    }

    /**
     * Validate Action
     */
    public function confirm()
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/aretoqp');

        $order_id = $this->session->data['order_id'];

        // Load Order
        $order = $this->model_checkout_order->getOrder($order_id);

	    $validate_url = $this->url->link('extension/payment/aretoqp/validate', '', 'SSL');

	    // Short URL to workaround Areto 3DS (query params are lost)
	    //$ch = curl_init();
	    //curl_setopt($ch,CURLOPT_URL, 'http://tinyurl.com/api-create.php?url=' . $validate_url);
	    //curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
	    //curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
	    //$validate_url = curl_exec($ch);
	    //curl_close($ch);

	    $url = 'https://pay.aretosystems.com/pwall/Api/v1/PaymentWall/QuickPay';

	    $fields = array(
		    'Id' => $this->config->get('aretoqp_api_id'),
		    'Session' => $this->config->get('aretoqp_api_session'),
		    'ExternalOrderId' => $order_id,
		    'Descriptor' => $this->config->get('aretoqp_descriptor'),
		    'Amount' => number_format($order['total'], 2),
		    'CurrencyCode' => $order['currency_code'],
		    'TermsConditionsUrl' => $this->config->get('aretoqp_terms_url'),
		    'ReturnUrl' => $validate_url,
	    );

	    echo '<br /><strong>Redirect to payment gateway...</strong>';
	    echo sprintf('<form id="areto_checkout" action="%s" method="%s">', $url, 'POST');
	    foreach ($fields as $key => $value) {
		    echo sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value);
	    }
	    echo '</form>';
	    echo '<script>document.getElementById(\'areto_checkout\').submit();</script>';
	    exit();
    }

    /**
     * Validate Action
     */
    public function validate()
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/aretoqp');

        $order_id = $this->session->data['order_id'];

        // Load Order
        $order = $this->model_checkout_order->getOrder($order_id);

	    if (!isset($_POST['InternalOrderID']) ||
	        !isset($_POST['ExternalOrderID']) ||
	        !isset($_POST['Result'])
	    ) {
		    $this->session->data['areto_error'] = 'Unable to process payment';
		    $this->response->redirect($this->url->link('extension/payment/aretoqp/error', '', 'SSL'));
		    exit();
	    }

	    if ($_POST['Result'] != '1') {
		    $this->session->data['areto_error'] = 'Payment failed';
		    $this->response->redirect($this->url->link('extension/payment/aretoqp/error', '', 'SSL'));
		    exit();
	    }

        // Get InternalOrderID value
        $internal_order_id = $_POST['InternalOrderID'];

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
				    $this->response->redirect($this->url->link('extension/payment/aretoqp/error', '', 'SSL'));
				    break;
			    case 1:
				    // Payment is success
				    // echo sprintf('Payment success: %s. InternalOrderID: %s', $result['Body']['OrderDescription'], $result['Body']['InternalOrderID']);
				    $this->addTransaction($order_id, $result['Body']['InternalOrderID']);
				    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('aretoqp_completed_status_id'), '', true);
				    $this->response->redirect($this->url->link('checkout/success', '', 'SSL'));
				    break;
			    default:
				    $message = 'Unknown order status';
				    $this->session->data['areto_error'] = $message;
				    $this->response->redirect($this->url->link('extension/payment/aretoqp/error', '', 'SSL'));
				    break;
		    }
	    } catch (Exception $e) {
		    $this->session->data['areto_error'] = $e->getMessage();
		    $this->response->redirect($this->url->link('extension/payment/aretoqp/error', '', 'SSL'));
	    }
    }

    /**
     * Error Action
     */
    public function error()
    {
        $this->load->language('extension/payment/aretoqp');

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
            $api_id = $this->config->get('aretoqp_api_id');
            $api_session = $this->config->get('aretoqp_api_session');
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