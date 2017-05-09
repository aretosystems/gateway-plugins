<?php

require_once DIR_SYSTEM . 'areto-sdk/vendor/php-credit-card-validator/src/CreditCard.php';
require_once DIR_SYSTEM . 'areto-sdk/Areto.php';

class ControllerExtensionPaymentAretocc extends Controller
{
    protected static $_api;

    private $error = array();

    protected $_options = array(
        'aretocc_api_id',
        'aretocc_api_session',
        'aretocc_total',
        'aretocc_completed_status_id',
        'aretocc_pending_status_id',
        'aretocc_canceled_status_id',
        'aretocc_failed_status_id',
        'aretocc_refunded_status_id',
        'aretocc_geo_zone_id',
        'aretocc_status',
        'aretocc_sort_order',
    );

    protected $_texts = array(
        'button_save',
        'button_cancel',
        'button_credit',
        'heading_title',
        'text_settings',
        'text_orders',
        'text_api_id',
        'text_api_session',
        'text_total',
        'text_complete_status',
        'text_pending_status',
        'text_canceled_status',
        'text_failed_status',
        'text_refunded_status',
        'text_geo_zone',
        'text_all_zones',
        'text_status',
        'text_enabled',
        'text_disabled',
        'text_sort_order',
        'text_success',
        'text_order_id',
        'text_actions',
        'text_wait',
        'text_internal_order_id',
        'text_refund',
        'text_captured',
        'text_canceled',
        'text_refunded',
    );

    /**
     * Constructor
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);

        // Install DB Tables
        $this->installDbTables();
    }

    /**
     * Index Action
     */
    function index()
    {
        $this->load->language('extension/payment/aretocc');
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/aretocc');
        $this->load->model('sale/order');

        $data['currency'] = $this->currency;

        $this->document->setTitle($this->language->get('heading_title'));

        // Load texts
        foreach ($this->_texts as $text) {
            $data[$text] = $this->language->get($text);
        }

        // Load options
        foreach ($this->_options as $option) {
            if (isset($this->request->post[$option])) {
                $data[$option] = $this->request->post[$option];
            } else {
                $data[$option] = $this->config->get($option);
            }
        }

        // Load config
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['action'] = $this->url->link('extension/payment/aretocc', 'token=' . $this->session->data['token'], 'SSL');
        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true);
        $data['error'] = $this->error;

        if (($this->request->server['REQUEST_METHOD'] === 'POST')) {
            if (isset($this->request->post['action'])) {
                $this->load->model('sale/order');

                $order_id = $this->request->post['order_id'];
                $internal_order_id = $this->request->post['internal_order_id'];
                $order = $this->model_sale_order->getOrder($order_id);

                if (!$order) {
                    $json = array(
                        'status' => 'error',
                        'message' => 'Invalid order Id'
                    );
                    $this->response->setOutput(json_encode($json));
                    return;
                }

                switch ($this->request->post['action']) {
                    case 'refund':
                        $total_refunded = $this->request->post['total_refunded'];

                        try {
                            $result = $this->getApi()->refund_request($internal_order_id, $total_refunded, 'Refund request by admin');
                            if (!$result) {
                                throw new Exception('Failed to perform refund');
                            }
                        } catch (Exception $e) {
                            $json = array(
                                'status' => 'error',
                                'message' => $e->getMessage()
                            );
                            $this->response->setOutput(json_encode($json));
                        }

                        // Set Order Status
                        $order_status_id = $data['aretocc_refunded_status_id'];
                        $this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id = '" . (int)$order_status_id . "', date_modified = NOW() WHERE order_id = '" . (int)$order_id . "'");
                        $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$order_status_id . "', date_added = NOW()");

                        $json = array(
                            'status' => 'ok',
                            'message' => 'Order successfully refunded.',
                            'label' => $data['text_refunded']
                        );
                        $this->response->setOutput(json_encode($json));
                        return;
                    default:
                        //
                }
            }

            if ($this->validate()) {
                $this->save();
            }
        }

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),
            'separator' => false
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/aretocc', 'token=' . $this->session->data['token'], 'SSL')
        );

        $data['orders'] = array();
        $query = sprintf("SELECT * FROM %saretocc_orders ORDER BY order_id DESC;", DB_PREFIX);
        $orders = $this->db->query($query);
        foreach ($orders->rows as $_key => $areto_order) {
            $order = $this->model_sale_order->getOrder($areto_order['order_id']);
            if (!$order) {
                continue;
            }

            $order['internal_order_id'] = $areto_order['internal_order_id'];
            $order['total_refunded'] = (float) $areto_order['total_refunded'];
            $order['total'] = $this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false);
            $order['order_link'] = $this->url->link('sale/order/info', 'token=' . $this->session->data['token'] . '&order_id=' . $order['order_id'], 'SSL');
            $data['orders'][] = $order;
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/payment/aretocc.tpl', $data));
    }

    /**
     * Validate configuration
     */
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/aretocc')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $api_id = $this->request->post['aretocc_api_id'];
        $api_session = $this->request->post['aretocc_api_session'];

        if (empty($api_id)) {
            $this->error['aretocc_api_id'] = $this->language->get('error_api_id');
        }

        if (empty($api_session)) {
            $this->error['aretocc_api_session'] = $this->language->get('error_api_session');
        }

        return !$this->error;
    }

    /**
     * Save configuration
     */
    protected function save()
    {
        $data = array();
        foreach ($this->_options as $option) {
            $data[$option] = $this->request->post[$option];
        }
        $this->model_setting_setting->editSetting('aretocc', $data);

        $this->session->data['success'] = $this->language->get('text_success');
        $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'], 'SSL'));
    }

    /**
     * Install Database Tables
     */
    public function installDbTables()
    {
        $res = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "aretocc_orders'");
        if ($res->num_rows === 0) {
            $this->db->query("
CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "aretocc_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `internal_order_id` int(11) DEFAULT NULL,
  `total_refunded` float DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `internal_order_id` (`internal_order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
            ");
        }
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

}
