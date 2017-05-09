<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../Areto.php';
require_once __DIR__ . '/../vendor/PHP-Name-Parser/parser.php';
require_once __DIR__ . '/../vendor/php-credit-card-validator/src/CreditCard.php';

// Detect credit card type
$card = Inacho\CreditCard::validCreditCard($_POST['card-number']);
if (!$card['valid']) {
    throw new Exception('Credit card number is invalid');
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

// Parse name field
$parser = new FullNameParser();
$name = $parser->parse_name($_POST['card-holder-name']);

$areto = new Areto();

// Set IDs
$areto->setEnvironment($api_id, $api_session);

$data = array(
    'order_id' => '1000001',
    'amount' => number_format($_POST['amount'], 2),
    'currency_code' => $_POST['currency_code'],
    'CVC' => $_POST['cvv'],
    'expiry_month' => $_POST['expiry-month'],
    'expiry_year' => $_POST['expiry-year'],
    'name' => $name['fname'],
    'surname' => $name['lname'],
    'number' => $_POST['card-number'],
    'type' => $type,
    'address' => $_POST['street'],
    'client_city' => $_POST['city'],
    'client_country_code' => $_POST['country'],
    'client_zip' => $_POST['zip_code'],
    'client_state' => $_POST['state'],
    'client_email' => $_POST['email'],
    'client_external_identifier' => '',
    'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
    'client_forward_IP' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
    'client_DOB' => '',
    'client_phone' => $_POST['phone'],
    'token' => '',
    'create_token' => '0',
    'return_url' => rtrim($base_url, '/') . '/redirect.php'
);

$result = $areto->sale_request($data);
if (!is_array($result) || !isset($result['Result'])) {
    throw new Exception('Unable process request: invalid response');
}

switch ((int)$result['Result']['Code']) {
    case 0:
        // Payment is failed
        echo sprintf('Payment failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']);
        break;
    case 1:
        // Payment is success
        echo sprintf('Payment success: %s. Internal OrderID: %s', $result['Result']['Description'], $result['Body']['InternalOrderID']);
        break;
    case 4:
        // Payment require 3D-Secure
        // Save InternalOrderID value
        setcookie('internal_order_id', $result['Body']['InternalOrderID']);

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
        break;
    default:
        echo sprintf('Error: %s. Code: %s', $result['Result']['Description'], $result['Result']['Code']);
        break;
}
