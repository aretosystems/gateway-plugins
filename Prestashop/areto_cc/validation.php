<?php

global $cookie;
require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/areto_cc.php';

session_start();

$cart_id = (int)$cookie->id_cart;
if (Tools::getValue('id_cart')) {
    $cart_id = Tools::getValue('id_cart');
}

$module = new areto_cc();
$cart = new Cart($cart_id);
if (!Validate::isLoadedObject($cart)) {
    Tools::redirect('index.php?controller=order&step=1');
}

$customer = new Customer((int)$cart->id_customer);
if (!Validate::isLoadedObject($customer)) {
    Tools::redirect('index.php?controller=order&step=1');
}

// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
$authorized = false;
foreach (Module::getPaymentModules() as $item) {
    if ($item['name'] == $module->name) {
        $authorized = true;
        break;
    }
}

if (!$authorized) {
    die($module->l('This payment method is not available.', 'validation'));
}

if (!isset($_SESSION['ARETO_ORDER_ID'])) {
    die($module->l('Wrong Internal Order ID.', 'validation'));
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
        $result = $module->getApi()->status_request($internal_order_id);

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
            //die(Tools::displayError($message));
            throw new Exception($message);
            break;
        case 1:
            // Payment is success
            // echo sprintf('Payment success: %s. InternalOrderID: %s', $result['Body']['OrderDescription'], $result['Body']['InternalOrderID']);

            // Place Order
            $amount = (float)$cart->getOrderTotal(true, Cart::BOTH);
            $module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $amount, $module->displayName, null, array(), null, true, $customer->secure_key);
            $order = new Order($module->currentOrder);
            if (!Validate::isLoadedObject($order)) {
                die(Tools::displayError($module->l('Unable to place order.')));
            }

            // Make Invoice
            $order->setInvoice(true);

            // Save Transaction
            $module->addTransaction($order->id, $result['Body']['InternalOrderID']);

            // Redirect to Order Confirmation
            $redirectUrl = _PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&key=' . $customer->secure_key . '&id_cart=' . (int)$cart->id . '&id_module=' . (int)$module->id . '&id_order=' . (int)$module->currentOrder;
            Tools::redirect($redirectUrl);

            break;
        default:
            $message = 'Unknown order status';
            //die(Tools::displayError($message));
            throw new Exception($message);
            break;
    }
} catch (Exception $e) {
    $module->displayError($e->getMessage());
    //die(Tools::displayError($e->getMessage()));
}
