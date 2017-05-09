<?php

class Areto_Payments_QuickpayController extends Mage_Core_Controller_Front_Action
{
    public function _construct()
    {
        // Bootstrap Environment
        Mage::getSingleton('areto/method_quickpay');
    }

    /**
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function redirectAction()
    {
        $session = Mage::getSingleton('checkout/session');

        // Load Order
        $order_id = $session->getLastRealOrderId();

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        if (!$order->getId()) {
            Mage::throwException('No order for processing found');
        }

        // Set quote to inactive
        Mage::getSingleton('checkout/session')->setAretoQuoteId(Mage::getSingleton('checkout/session')->getQuoteId());
        Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
        Mage::getSingleton('checkout/session')->clear();

        $payment = $order->getPayment();

        $url = 'https://pay.aretosystems.com/pwall/Api/v1/PaymentWall/QuickPay';

        $fields = array(
            'Id' => $payment->getMethodInstance()->getConfigData('api_id'),
            'Session' => $payment->getMethodInstance()->getConfigData('api_session'),
            'ExternalOrderId' => $order_id,
            'Descriptor' => $payment->getMethodInstance()->getConfigData('descriptor'),
            'Amount' => number_format($order->getGrandTotal(), 2),
            'CurrencyCode' => $order->getOrderCurrency()->getCurrencyCode(),
            'TermsConditionsUrl' => $payment->getMethodInstance()->getConfigData('terms_url'),
            'ReturnUrl' => Mage::getUrl('areto/quickpay/success', array('_secure' => true)),
        );

        echo '<br /><strong>Redirect to payment gateway...</strong>';
        echo sprintf('<form id="areto_checkout" action="%s" method="%s">', $url, 'POST');
        foreach ($fields as $key => $value) {
            echo sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value);
        }
        echo '</form>';
        echo '<script>document.getElementById(\'areto_checkout\').submit();</script>';
        exit();
    }

    /**
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    public function successAction()
    {
        if (empty($_POST['InternalOrderID']) &&
            empty($_POST['ExternalOrderID']) &&
            empty($_POST['Result'])
        ) {
            // Set quote to active
            if ($quoteId = Mage::getSingleton('checkout/session')->getAretoQuoteId()) {
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                if ($quote->getId()) {
                    $quote->setIsActive(true)->save();
                    Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
                }
            }

            Mage::getSingleton('checkout/session')->addError('Failed to complete payment');
            return;
        }

        if (isset($_POST['Result']) && $_POST['Result'] != '1') {
            // Set quote to active
            if ($quoteId = Mage::getSingleton('checkout/session')->getAretoQuoteId()) {
                $quote = Mage::getModel('sales/quote')->load($quoteId);
                if ($quote->getId()) {
                    $quote->setIsActive(true)->save();
                    Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
                }
            }

            Mage::getSingleton('checkout/session')->addError('Payment failed');
            return;
        }

        // Load Order
        $order_id = $_POST['ExternalOrderID'];

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($order_id);
        if (!$order->getId()) {
            Mage::throwException('No order for processing found');
        }

        $internal_order_id = $_POST['InternalOrderID'];

        // Wait for order status != 4
        set_time_limit(0);
        $times = 0;
        do {
            $times++;
            if ($times > 6) {
                $this->_error('Status check timeout', $order_id);
                return;
            }
            sleep(10);
            $result = Mage::helper('areto')->getApi()->status_request($internal_order_id);

            // Check request is failed
            if ((int)$result['Result']['Code'] !== 1) {
                $message = Mage::helper('areto')->__('Status Check is failed: %s. Code: %s.',
                    $result['Result']['Description'],
                    $result['Result']['Code']
                );
                $this->_error($message, $order_id);
                return;
            }

            $order_status = (int)$result['Body']['OrderStatus'];

        } while ($order_status === 4);

        /**
         *
         * 0 or 2 Rejected purchase
         * 1 Approved purchase
         * 4 3D Secure authentication redirection available
         * 6 Approved refund
         * 7 Rejected refund
         * 8007 Validation errors - invalid amount, email address etc
         * 8008 Order was outside limits
         * 8009 Authentication error
         * 8010 Function not supported
         * 8011 Generic error
         * 8012 Invalid order ID
         */

        switch ((int)$result['Body']['OrderStatus']) {
            case 0:
                // Payment has been declined
                $message = Mage::helper('areto')->__('Payment has been declined');
                $this->_error($message, $order_id);
                break;
            case 1;
                // Payment is success
                $message = Mage::helper('areto')->__('Payment success: %s. InternalOrderID: %s',
                    $result['Body']['OrderDescription'],
                    $result['Body']['InternalOrderID']
                );

                // Select Order Status
                $new_status = $order->getPayment()->getMethodInstance()->getConfigData('order_status');

                // Get Order Status
                /** @var Mage_Sales_Model_Order_Status $status */
                $status = Mage::helper('areto')->getAssignedStatus($new_status);

                // Change order status
                $order->setData('state', $status->getState());
                $order->setStatus($status->getStatus());
                $order->addStatusHistoryComment($message, $new_status);

                // Save transaction
                Mage::helper('areto')->createTransaction($order->getPayment(),
                    null,
                    $result['Body']['InternalOrderID'],
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_PAYMENT, 1
                );

                $invoice = Mage::helper('areto')->makeInvoice($order, false);
                $invoice->setTransactionId($result['Body']['InternalOrderID']);
                $invoice->save();

                $order->save();
                $order->sendNewOrderEmail();

                // Redirect to Success Page
                Mage::getSingleton('checkout/session')->setLastSuccessQuoteId(Mage::getSingleton('checkout/session')->getAretoQuoteId());
                $this->_redirect('checkout/onepage/success', array('_secure' => true));
                break;
            default:
                $message = Mage::helper('areto')->__('Unknown order status');
                $this->_error($message, $order_id);
                break;
        }
    }

    /**
     * Error
     * @param $message
     * @param null $order_id
     */
    protected function _error($message, $order_id = null)
    {
        // Load Order
        if ($order_id) {
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($order_id);
            if ($order->getId()) {
                // Cancel order
                $order->cancel();
                $order->addStatusHistoryComment($message, Mage_Sales_Model_Order::STATE_CANCELED);
                $order->save();
            }
        }

        // Set quote to active
        if ($quoteId = Mage::getSingleton('checkout/session')->getAretoQuoteId()) {
            $quote = Mage::getModel('sales/quote')->load($quoteId);
            if ($quote->getId()) {
                $quote->setIsActive(true)->save();
                Mage::getSingleton('checkout/session')->setQuoteId($quoteId);
            }
        }

        Mage::getSingleton('checkout/session')->addError($message);
        $this->_redirect('checkout/cart');
        return;
    }
}
