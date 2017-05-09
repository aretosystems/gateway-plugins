<?php

namespace Areto\Payments\Controller\Qp;

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

        /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
        $method = $order->getPayment()->getMethodInstance();

        $url = 'https://pay.aretosystems.com/pwall/Api/v1/PaymentWall/QuickPay';

        $fields = array(
            'Id' => $method->getConfigData('api_id'),
            'Session' => $method->getConfigData('api_session'),
            'ExternalOrderId' => $order->getIncrementId(),
            'Descriptor' => $method->getConfigData('descriptor'),
            'Amount' => number_format($order->getGrandTotal(), 2),
            'CurrencyCode' => $order->getOrderCurrency()->getCurrencyCode(),
            'TermsConditionsUrl' => $method->getConfigData('terms_url'),
            'ReturnUrl' => $this->urlBuilder->getUrl('areto/qp/validate', [
                '_secure' => $this->getRequest()->isSecure()
            ]),
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
