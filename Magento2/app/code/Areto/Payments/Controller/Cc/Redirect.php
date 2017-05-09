<?php

namespace Areto\Payments\Controller\Cc;

use Magento\Sales\Model\Order\Payment\Transaction;

class Redirect extends \Magento\Framework\App\Action\Action
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

        // Get Areto Internal Order Id
        $internal_order_id = $this->session->getAretoOrderId();
        if (empty($internal_order_id)) {
            $this->session->restoreQuote();
            $this->messageManager->addError(__('Invalid Internal Order Id'));
            $this->_redirect('checkout/cart');
            return;
        }

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();

        try {
            // Init Areto API
            $api_id = $method->getConfigData('api_id');
            $api_session = $method->getConfigData('api_session');
            $this->helper->getApi()->setEnvironment($api_id, $api_session);

            // Wait for order status != 4
            set_time_limit(0);
            $times = 0;
            do {
                $times++;
                if ($times > 6) {
                    throw new \Exception('Status check timeout');
                }
                sleep(10);
                $result = $this->helper->getApi()->status_request($internal_order_id);

                // Check request is failed
                if ((int)$result['Result']['Code'] !== 1) {
                    throw new \Exception(sprintf('Status Check is failed: %s. Code: %s.', $result['Result']['Description'], $result['Result']['Code']));
                }

                $order_status = (int)$result['Body']['OrderStatus'];
            } while ($order_status === 4);

            switch ((int)$result['Body']['OrderStatus']) {
                case 0:
                    // Payment has been declined
                    $message = 'Payment has been declined';
                    throw new \Exception($message);
                    break;
                case 1:
                    // Payment is success
                    // echo sprintf('Payment success: %s. InternalOrderID: %s', $result['Body']['OrderDescription'], $result['Body']['InternalOrderID']);

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
                default:
                    $message = 'Unknown order status';
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
