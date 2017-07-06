<?php
/**
 * @package AretoPay
 * @author Areto Systems Limited
 * @license https://www.prestashop.com/en/osl-license Open Software License (OSL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class areto_cc extends PaymentModule
{
    private $_html = '';
    private $_postErrors = array();

    protected $_api;
    protected $_log;

    public $api_id;
    public $api_session;

    /**
     * Init
     */
    public function __construct()
    {
        $this->name = 'areto_cc';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Areto Systems Limited';

        $this->currencies = true; // binding this method of payment to a specific currency
        $this->currencies_mode = 'checkbox';

        // Init Configuration
        $config = Configuration::getMultiple(array('ARETO_API_ID', 'ARETO_API_SESSION'));
        $this->api_id = isset($config['ARETO_API_ID']) ? $config['ARETO_API_ID'] : '';
        $this->api_session = isset($config['ARETO_API_SESSION']) ? $config['ARETO_API_SESSION'] : '';

        // Init API
        $this->getApi()->setEnvironment($this->api_id, $this->api_session);

        parent::__construct();

        $this->displayName = $this->l('AretoPay Credit Card Payments');
        $this->description = $this->l('Start accepting online credit card payments through AretoPay');
        $this->confirmUninstall = $this->l('Are you sure about removing these details?');

        // Some checks...
        if (empty($this->api_id) || empty($this->api_session)) {
            $this->warning[] = $this->l('API ID and API Session must be configured before using this module.');
        }

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning[] = $this->l('No currency has been set for this module.');
        }
    }

    /**
     * Install Action
     * @return bool
     */
    public function install()
    {
        if (!parent::install()
            || !$this->registerHook('header')
            || !$this->registerHook('payment')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('adminOrder')
            || !$this->registerHook('BackOfficeHeader')
            || !$this->registerHook('DisplayHeader'))
        {
            return false;
        }

        /* The SOAP PHP extension must be enabled to use this module */
        if (!extension_loaded('soap')) {
            $this->_errors[] = $this->l('Sorry, this module requires the SOAP PHP Extension (http://www.php.net/soap), which is not enabled on your server. Please ask your hosting provider for assistance.');
            return false;
        }

        /* The OpenSSL PHP extension must be enabled to use this module */
        if (!extension_loaded('openssl')) {
            $this->_errors[] = $this->l('Sorry, this module requires the OpenSSL PHP Extension (http://www.php.net/manual/en/openssl.installation.php), which is not enabled on your server. Please ask your hosting provider for assistance.');
            return false;
        }

        // Create table for Transactions
        Db::getInstance()->execute("
CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "aretocc_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `internal_order_id` int(11) DEFAULT NULL,
  `total_refunded` float DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `internal_order_id` (`internal_order_id`)
) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;
");

        // Set Payment Settings
        Configuration::updateValue('ARETO_API_ID', '');
        Configuration::updateValue('ARETO_API_SESSION', '');
        return true;
    }

    /**
     * Uninstall Action
     * @return bool
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        return true;
    }

    private function _postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('api_id')) {
                $this->_postErrors[] = $this->l('API ID are required.');
            }

            if (!Tools::getValue('api_session')) {
                $this->_postErrors[] = $this->l('API Session is required.');
            }
        }
    }

    private function _postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('ARETO_API_ID', Tools::getValue('api_id'));
            Configuration::updateValue('ARETO_API_SESSION', Tools::getValue('api_session'));
        }
        $this->_html .= '<div class="conf confirm"> ' . $this->l('Settings updated') . '</div>';
    }

    private function _displayForm()
    {
        $this->_html .= '<img src="../modules/areto_cc/logo.png" style="float:left; margin-right:15px;" width="86" height="49"><b>'
            . $this->l('This module allows you to accept online credit card payments through') . ' <a href="http://www.aretosystems.com">AretoPay</a></b><br /><br />';

        $this->_html .=
            '<form action="' . Tools::htmlentitiesUTF8($_SERVER['REQUEST_URI']) . '" method="post">
			<fieldset>
			<legend><img src="../img/admin/contact.gif" />' . $this->l('Settings') . '</legend>
				<table border="0" width="500" cellpadding="0" cellspacing="0" id="form">
					<tr>
					<td colspan="2">' . $this->l('Please specify Areto account details.') . '.<br /><br />
					</td>
					</tr>
					<tr>
					    <td width="130" style="height: 35px;">' . $this->l('API ID') . '</td>
					    <td><input type="text" name="api_id" value="' . htmlentities(Tools::getValue('api_id', $this->api_id), ENT_COMPAT, 'UTF-8') . '" style="width: 300px;" /></td>
					    </tr>
					<tr>
						<td width="130" style="vertical-align: top;">' . $this->l('API Session') . '</td>
						<td><input type="text" name="api_session" value="' . htmlentities(Tools::getValue('api_session', $this->api_session), ENT_COMPAT, 'UTF-8') . '" style="width: 300px;" /></td>
					</tr>
					<tr><td colspan="2" align="center"><input class="button" name="btnSubmit" value="' . $this->l('Update settings') . '" type="submit" /></td></tr>
				</table>
			</fieldset>
		</form>';
    }

    public function getContent()
    {
        $this->_html = '<h2>' . $this->displayName . '</h2>';

        if (Tools::isSubmit('btnSubmit')) {
            $this->_postValidation();
            if (!count($this->_postErrors)) {
                $this->_postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= '<div class="alert error">' . $err . '</div>';
                }
            }
        } else {
            $this->_html .= '<br />';
        }

        $this->_displayForm();

        return $this->_html;
    }

    /**
     * Get Areto handler
     * @return Areto
     */
    public function getApi()
    {
        if (!$this->_api) {
            if (!class_exists('Areto', false)) {
                require_once _PS_ROOT_DIR_ . '/vendor/areto/areto-sdk/Areto.php';
            }

            $this->_api = new Areto;
        }

        return $this->_api;
    }

    public function hookPayment($params)
    {

        if (!$this->active) {
            return;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/'
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * Hook: Payment Return
     * @param $params
     * @return bool
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $order = $params['objOrder'];
        switch ($order->current_state) {
            case Configuration::get('PS_OS_PAYMENT'):
                $status = 'ok';
                break;
            default:
                $status = 'error';
        }

        $this->smarty->assign(array(
            'status' => $status,
            'id_order' => $order->id
        ));

        if (property_exists($order, 'reference') && !empty($order->reference)) {
            $this->smarty->assign('reference', $order->reference);
        }

        return $this->display(__FILE__, 'confirmation.tpl');
    }

    /**
     * Hook: AdminOrder details
     */
    public function hookAdminOrder($params)
    {
        $order_id = !empty($_GET['id_order']) ? (int)$_GET['id_order'] : 0;
        $order = new Order($order_id);

        /* Check if the order was paid with this Addon and display the Transaction details */
        if ($order->module === $this->name) {
            // Retrieve the transaction details
            $areto_order = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . 'aretocc_orders WHERE order_id = ' . (int) $order_id . ';');
            if ($areto_order) {
                $this->context->smarty->assign(array(
                    'order_id' => $order_id,
                    'order_amount' => $order->total_paid,
                    'internal_order_id' => $areto_order['internal_order_id'],
                    'total_refunded' => (float) ($order->total_paid - $areto_order['total_refunded'])
                ));

                return $this->display(__FILE__, 'admin-order.tpl');
            }
        }
    }


    /**
     * Hook: BackOfficeHeader
     */
    public function hookBackOfficeHeader()
    {
        /* Continue only if we are on the order's details page (Back-office) */
        if (!isset($_GET['vieworder']) || !isset($_GET['id_order'])) {
            return;
        }

        $order = new Order($_GET['id_order']);
        if ($order->module !== $this->name) {
            return;
        }

        // Refund Action
        if (Tools::isSubmit('process_refund') && isset($_POST['refund_amount']) && isset($_POST['internal_order_id'])) {
            $order_id = $_POST['areto_order_id'];
            $internal_order_id = $_POST['internal_order_id'];
            $refund_amount = (float)$_POST['refund_amount'];

            $order = new Order($order_id);
            $order_fields = $order->getFields();
            if ($refund_amount > $order_fields['total_paid'] || $refund_amount <= 0) {
                $output = array(
                    'status' => 'error',
                    'message' => $this->l('Wrong refund amount.')
                );
                die(Tools::jsonEncode($output));
            }

            try {
                $result = $this->getApi()->refund_request($internal_order_id, $refund_amount, 'Refund from PrestaShop Backoffice');
                if (!$result) {
                    throw new Exception('Failed to perform refund');
                }
            } catch (Exception $e) {
                $json = array(
                    'status' => 'error',
                    'message' => $e->getMessage()
                );
                die(Tools::jsonEncode($json));
            }

            // Set Order Status
            $order->setCurrentState(Configuration::get('PS_OS_REFUND'));

            $json = array(
                'status' => 'ok',
                'message' => $this->l('Order successfully refunded.')
            );
            die(Tools::jsonEncode($json));
        }
    }

    /**
     * Hook: DisplayHeader
     * @param $params
     */
    public function hookDisplayHeader($params)
    {
        $this->context->controller->addJqueryUI(array('ui.datepicker'));
        $this->context->controller->addCSS('//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css', 'all');
        $this->context->controller->addCSS($this->_path . 'css/style.css', 'all');
        $this->context->controller->addJS($this->_path . 'js/jquery.validate.min.js');
        $this->context->controller->addJS($this->_path . 'js/additional-methods.min.js');
        $this->context->controller->addJS($this->_path . 'js/jquery.payment.min.js');
        $this->context->controller->addJS($this->_path . 'js/script.js');
    }

    /**
     * Check Currency is supported
     * @param $cart
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Save Internal Order Id
     * @param $order_id
     * @param $internal_order_id
     */
    public function addTransaction($order_id, $internal_order_id)
    {
        // Save Transaction
        if (!Db::getInstance()->Execute(sprintf('INSERT INTO `' . _DB_PREFIX_ . 'aretocc_orders` (order_id, internal_order_id) VALUES (%s, %s);',
            pSQL((int)$order_id), pSQL((int)$internal_order_id)))
        ) {
            die(Tools::displayError('Error when executing database query'));
        }
    }

    /**
     * Display Error
     * @todo
     * @param $message
     * @return mixed
     */
    public function displayError($message)
    {
        //$this->context->smarty->assign(array(
        //    'message' => $message,
        //));

        //return $this->display(__FILE__, 'error.tpl');

        @session_start();
        $_SESSION['message'] = $message;
        $link = $this->context->link->getModuleLink('areto_cc', 'error');
        Tools::redirect($link);
    }
}
