<?php

global $cookie;
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/areto_cc.php';
require_once _PS_ROOT_DIR_ . '/vendor/php-credit-card-validator/src/CreditCard.php';
session_start();

$module = new areto_cc();
$cart = new Cart((int)$cookie->id_cart);
if (!Validate::isLoadedObject($cart)) {
    Tools::redirect('index.php?controller=order&step=1');
}

$customer = new Customer((int)$cart->id_customer);
if (!Validate::isLoadedObject($customer)) {
    Tools::redirect('index.php?controller=order&step=1');
}

$currency = Currency::getCurrency((int)$cart->id_currency);
$lang = Language::getLanguage((int)$cart->id_lang);
$billing_address = new Address((int)$cart->id_address_invoice);
$amount = (float)$cart->getOrderTotal(true, Cart::BOTH);

// Get Credit Card Fields
$cardNumber = preg_replace('/\s+/', '', Tools::getValue('cardNumber'));
$cardExpiry = preg_replace('/\s+/', '', Tools::getValue('cardExpiry'));
$cardCVC = Tools::getValue('cardCVC');
$cardExpiry = explode('/' ,$cardExpiry);

// Get DOB
$date_of_birth = Tools::getValue('date_of_birth');
$date_of_birth = date('Y-m-d', strtotime($date_of_birth));

// Phone
$phone = Tools::getValue('phone');
if (empty($phone)) {
    $phone = $billing_address->phone_mobile;
}

// Detect credit card type
$card = Inacho\CreditCard::validCreditCard($cardNumber);
if (!$card['valid']) {
    Tools::redirect('index.php?controller=order&step=1');
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

// Return URL
$validate_url = _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/areto_cc/validation.php?id_cart=' . $cart->id;

// Get Client State
$client_state = (string)State::getNameById($billing_address->id_state);
if ((string)Country::getIsoById($billing_address->id_country) === 'US') {
    $result = Db::getInstance()->getRow('
		SELECT `iso_code`
		FROM `'._DB_PREFIX_.'state`
		WHERE `id_state` = '.intval($billing_address->id_state));
    $client_state = $result['iso_code'];
}

// Sale Request
$data = array(
    'order_id' => $cart->id,
    'amount' => number_format($amount, 2),
    'currency_code' => $currency['iso_code'],
    'CVC' => $cardCVC,
    'expiry_month' => $cardExpiry[0],
    'expiry_year' => strlen($cardExpiry) < 4 ? 2000 + $cardExpiry[1] : $cardExpiry[1],
    'name' => $billing_address->firstname,
    'surname' => $billing_address->lastname,
    'number' => $cardNumber,
    'type' => $type,
    'address' => trim($billing_address->address1 . ' ' . $billing_address->address2),
    'client_city' => $billing_address->city,
    'client_country_code' => (string)Country::getIsoById($billing_address->id_country),
    'client_zip' => $billing_address->postcode,
    'client_state' => $client_state,
    'client_email' => $customer->email,
    'client_external_identifier' => '',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
    'client_forward_IP' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
    'client_DOB' => $date_of_birth,
    'client_phone' => $phone,
    'token' => '',
    'create_token' => '0',
    'return_url' => $validate_url
);

try {
    $result = $module->getApi()->sale_request($data);
    if (!is_array($result) || !isset($result['Result'])) {
        throw new Exception('Unable process request: invalid response');
    }

    switch ((int)$result['Result']['Code']) {
        case 0:
            // Payment is failed
            $message = sprintf('Payment failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']);
            //die(Tools::displayError($message));
            throw new Exception($message);
            break;
        case 1:
            // Payment is success
            //echo sprintf('Payment success: %s. Internal OrderID: %s', $result['Result']['Description'], $result['Body']['InternalOrderID']);

            // Place Order
            $module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $amount, $module->displayName, null, array(), null, true, $customer->secure_key);
            $order = new Order($module->currentOrder);
            if (!Validate::isLoadedObject($order)) {
                die(Tools::displayError($this->l('Unable to place order.')));
            }

            // Make Invoice
            $order->setInvoice(true);

            // Save Transaction
            $module->addTransaction($order->id, $result['Body']['InternalOrderID']);

            // Redirect to Order Confirmation
            $redirectUrl = _PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&key=' . $customer->secure_key . '&id_cart=' . (int)$cart->id . '&id_module=' . (int)$module->id . '&id_order=' . (int)$module->currentOrder;
            Tools::redirect($redirectUrl);

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
            //die(Tools::displayError($message));
            throw new Exception($message);
            break;
    }
} catch (Exception $e) {
    $module->displayError($e->getMessage());
    //die(Tools::displayError($e->getMessage()));
}



