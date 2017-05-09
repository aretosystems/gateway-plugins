<?php

namespace Areto\Payments\Controller\Cc;

if (!class_exists('\Inacho\CreditCard', false)) {
    require_once dirname(__FILE__) . '/../../vendor/php-credit-card-validator/src/CreditCard.php';
}

use Magento\Sales\Model\Order\Payment\Transaction;

class Payment extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Areto\Payments\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\OrderSender
     */
    protected $orderSender;

    /**
     * Constructor
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $session
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $session,
        \Areto\Payments\Helper\Data $helper,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender
    )
    {
        parent::__construct($context);

        $this->urlBuilder = $context->getUrl();
        $this->session = $session;
        $this->helper = $helper;
        $this->orderSender = $orderSender;
    }

    public function execute()
    {
        // Load Order
        $order = $this->getOrder();
        if (!$order->getId()) {
            $this->session->restoreQuote();
            $this->messageManager->addError(__('No order for processing found'));
            $this->_redirect('checkout/cart');
            return;
        }

        // Get Areto CC Details
        $cc = $this->session->getAretoCCData();
        if (empty($cc)) {
            $this->session->restoreQuote();
            $this->messageManager->addError(__('Please enter CC details'));
            $this->_redirect('checkout/cart');
            return;
        }

        // Get Credit Card Fields
        $cardNumber = preg_replace('/\s+/', '', $cc['cc_number']);
        $cardExpiry = preg_replace('/\s+/', '', $cc['cc_expiry']);
        $cardCVC = $cc['cc_cvc'];
        $cardExpiry = explode('/', $cardExpiry);

        // Get DOB
        $date_of_birth = $cc['cc_dob'];
        $date_of_birth = date('Y-m-d', strtotime($date_of_birth));

        // Detect credit card type
        $card = \Inacho\CreditCard::validCreditCard($cardNumber);
        if (!$card['valid']) {
            $this->session->restoreQuote();
            $this->messageManager->addError(__('Invalid Credit Card Number'));
            $this->_redirect('checkout/cart');
            return;
        }

        $types = [
            'visaelectron' => 'VisaElectron',
            'maestro' => 'MAES',
            'visa' => 'VISA',
            'mastercard' => 'MC',
            'amex' => 'AMEX',
            'dinersclub' => 'DINER',
            'discover' => 'DISC',
            'unionpay' => 'CUP',
            'jcb' => 'JCB',
        ];

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();

        // Sale Request
        $data = [
            'order_id' => $order->getIncrementId(),
            'amount' => number_format($order->getGrandTotal(), 2),
            'currency_code' => $order->getOrderCurrency()->getCurrencyCode(),
            'CVC' => $cardCVC,
            'expiry_month' => $cardExpiry[0],
            'expiry_year' => strlen($cardExpiry[1]) < 4 ? 2000 + $cardExpiry[1] : $cardExpiry[1],
            'name' => $order->getBillingAddress()->getFirstname(),
            'surname' => $order->getBillingAddress()->getLastname(),
            'number' => $cardNumber,
            'type' => isset($types[$card['type']]) ? $types[$card['type']] : strtoupper($card['type']),
            'address' => trim(implode(',', $order->getBillingAddress()->getStreet())),
            'client_city' => (string)$order->getBillingAddress()->getCity(),
            'client_country_code' => $order->getBillingAddress()->getCountryId(),
            'client_zip' => (string)$order->getBillingAddress()->getPostcode(),
            'client_state' => (string)$order->getBillingAddress()->getRegionCode(),
            'client_email' => (string)$order->getBillingAddress()->getEmail(),
            'client_external_identifier' => '',
            'client_ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'client_forward_IP' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
            'client_DOB' => $date_of_birth,
            'client_phone' => (string)$order->getBillingAddress()->getTelephone(),
            'token' => '',
            'create_token' => '0',
            'return_url' => $this->urlBuilder->getUrl('areto/cc/redirect', [
                '_secure' => $this->getRequest()->isSecure()
            ])
        ];

        try {
            // Init Areto API
            $api_id = $method->getConfigData('api_id');
            $api_session = $method->getConfigData('api_session');
            $this->helper->getApi()->setEnvironment($api_id, $api_session);

            $result = $this->helper->getApi()->sale_request($data);
            if (!is_array($result) || !isset($result['Result'])) {
                throw new \Exception('Unable process request: invalid response');
            }

            switch ((int)$result['Result']['Code']) {
                case 0:
                    // Payment is failed
                    $message = sprintf('Payment failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']);
                    throw new \Exception($message);
                    break;
                case 1:
                    // Payment is success
                    //echo sprintf('Payment success: %s. Internal OrderID: %s', $result['Result']['Description'], $result['Body']['InternalOrderID']);

                    // Register Transaction
                    $transaction_id = $result['Body']['InternalOrderID'];
                    $order->getPayment()->setTransactionId($transaction_id);
                    $transaction = $order->getPayment()->addTransaction(Transaction::TYPE_PAYMENT, null, true);
                    $transaction->setIsClosed(0);
                    $transaction->save();

                    // Set Last Transaction ID
                    $order->getPayment()->setLastTransId($transaction_id)->save();

                    // Payment captured
                    $message = __('Payment has been captured');

                    // Change order status
                    $new_status = $method->getConfigData('order_status');

                    /** @var \Magento\Sales\Model\Order\Status $status */
                    $status = $this->helper->getAssignedState($new_status);
                    $order->setData('state', $status->getState());
                    $order->setStatus($status->getStatus());
                    $order->save();
                    $order->addStatusHistoryComment($message);

                    // Send order notification
                    try {
                        $this->orderSender->send($order);
                    } catch (\Exception $e) {
                        $this->_objectManager->get('Psr\Log\LoggerInterface')->critical($e);
                    }

                    // Create Invoice for Sale Transaction
                    $invoice = $this->helper->makeInvoice($order, [], false, $message);
                    $invoice->setTransactionId($transaction_id);
                    $invoice->save();

                    // Redirect to Success page
                    $this->session->getQuote()->setIsActive(false)->save();
                    $this->_redirect('checkout/onepage/success');
                    break;
                case 4:
                    // Payment require 3D-Secure
                    // Save InternalOrderID value
                    $this->session->setAretoOrderId($result['Body']['InternalOrderID']);

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
                    throw new \Exception($message);
                    break;
            }
        } catch (\Exception $e) {
            $this->session->restoreQuote();
            $this->messageManager->addError($e->getMessage());
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Get order object
     * @return \Magento\Sales\Model\Order
     */
    protected function getOrder()
    {
        $incrementId = $this->getCheckout()->getLastRealOrderId();
        $orderFactory = $this->_objectManager->get('Magento\Sales\Model\OrderFactory');
        return $orderFactory->create()->loadByIncrementId($incrementId);
    }

    /**
     * Get Checkout Session
     * @return \Magento\Checkout\Model\Session
     */
    protected function getCheckout()
    {
        return $this->_objectManager->get('Magento\Checkout\Model\Session');
    }
}
