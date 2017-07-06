<?php

global $cookie;
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/areto_quickpay.php';

session_start();

$module = new areto_quickpay();
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

$url = 'https://pay.aretosystems.com/pwall/Api/v1/PaymentWall/QuickPay';

$fields = array(
    'Id' => $module->api_id,
    'Session' => $module->api_session,
    'ExternalOrderId' => $cart->id,
    'Descriptor' => $module->descriptor,
    'Amount' => number_format($amount, 2),
    'CurrencyCode' => $currency['iso_code'],
    'TermsConditionsUrl' => $module->terms_url,
    'ReturnUrl' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/areto_quickpay/validation.php?id_cart=' . $cart->id,
);

echo '<br /><strong>Redirect to payment gateway...</strong>';
echo sprintf('<form id="areto_checkout" action="%s" method="%s">', $url, 'POST');
foreach ($fields as $key => $value) {
    echo sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value);
}
echo '</form>';
echo '<script>document.getElementById(\'areto_checkout\').submit();</script>';
exit();
