<?php

require_once DIR_SYSTEM . 'areto-sdk/Areto.php';

class ControllerExtensionPaymentAretoqp extends Controller
{
    protected static $_api;

    private $error = array();

    protected $_options = array(
        'aretoqp_api_id',
        'aretoqp_api_session',
	    'aretoqp_terms_url',
	    'aretoqp_descriptor',
        'aretoqp_total',
        'aretoqp_completed_status_id',
        'aretoqp_pending_status_id',
        'aretoqp_canceled_status_id',
        'aretoqp_failed_status_id',
        'aretoqp_refunded_status_id',
        'aretoqp_geo_zone_id',
        'aretoqp_status',
        'aretoqp_sort_order',
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
	    'text_terms_url',
	    'text_descriptor',
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
        $this->load->language('extension/payment/aretoqp');
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/aretoqp');
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

        $data['action'] = $this->url->link('extension/payment/aretoqp', 'token=' . $this->session->data['token'], 'SSL');
        $data['cancel'] = $this->url->link('extension/extension', 'token=' . $this->session->data['token'] . '&type=payment', true);
        $data['error'] = $this->error;

        if (($this->request->server['REQUEST_METHOD'] === 'POST')) {
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
            'href' => $this->url->link('extension/payment/aretoqp', 'token=' . $this->session->data['token'], 'SSL')
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/payment/aretoqp.tpl', $data));
    }

    /**
     * Validate configuration
     */
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/aretoqp')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $api_id = $this->request->post['aretoqp_api_id'];
        $api_session = $this->request->post['aretoqp_api_session'];
        $terms_url = $this->request->post['aretoqp_terms_url'];

        if (empty($api_id)) {
            $this->error['aretoqp_api_id'] = $this->language->get('error_api_id');
        }

        if (empty($api_session)) {
            $this->error['aretoqp_api_session'] = $this->language->get('error_api_session');
        }

        if (empty($terms_url)) {
	        $this->error['aretoqp_terms_url'] = $this->language->get('error_terms_url');
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
        $this->model_setting_setting->editSetting('aretoqp', $data);

        $this->session->data['success'] = $this->language->get('text_success');
        $this->response->redirect($this->url->link('extension/extension', 'token=' . $this->session->data['token'], 'SSL'));
    }

    /**
     * Install Database Tables
     */
    public function installDbTables()
    {
        $res = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "aretoqp_orders'");
        if ($res->num_rows === 0) {
            $this->db->query("
CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "aretoqp_orders` (
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
            $api_id = $this->config->get('aretoqp_api_id');
            $api_session = $this->config->get('aretoqp_api_session');
            self::$_api = new Areto;
            self::$_api->setEnvironment($api_id, $api_session);
        }

        return self::$_api;
    }

}
