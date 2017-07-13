<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../Areto.php';

$areto = new Areto();

// Set IDs
$areto->setEnvironment($api_id, $api_session);

$internal_order_id = $_COOKIE['internal_order_id'];
$result = $areto->status_request($internal_order_id);

// Check request is failed
if ((int)$result['Result']['Code'] !== 1) {
    echo sprintf('Status Check is failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']);
    exit();
}

switch ((int)$result['Body']['OrderStatus']) {
    case 0:
        // Payment has been declined
        echo 'Payment has been declined';
        break;
    case 1;
	  // Payment is success
        echo sprintf('Payment success: %s. InternalOrderID: %s', $result['Body']['OrderDescription'], $result['Body']['InternalOrderID']);
        break;
    case 4:
      // pending
    default:
        echo 'Unknown order status';
        break;
}
