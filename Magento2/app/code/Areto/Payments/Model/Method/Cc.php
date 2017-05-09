<?php

namespace Areto\Payments\Model\Method;

use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Online\GatewayInterface;
use Magento\Payment\Model\Method\ConfigInterface;
use \Magento\Framework\Exception\LocalizedException;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Class Cc
 * @package Areto\Payments\Model\Method
 */
class Cc extends AbstractMethod implements GatewayInterface
{
    const METHOD_CODE = 'areto_cc';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = self::METHOD_CODE;

    /**
     * @var string
     */
    //protected $_formBlockType = 'Areto\Payments\Block\Form\Cc';
    protected $_infoBlockType = 'Areto\Payments\Block\Info\Cc';

    /**
     * Payment Method feature
     *
     * @var bool
     */
    protected $_canAuthorize = false;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canCaptureOnce = true;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canVoid = true;
    protected $_canUseInternal = false;
    protected $_canFetchTransactionInfo = true;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Payment\Model\Method\Logger
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\ProductMetadata
     */
    protected $productMetadata;

    /**
     * @var \Areto\Payments\Helper\Data
     */
    protected $helper;

    /**
     * Constructor
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Locale\ResolverInterface $resolver
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     * @param \Areto\Payments\Helper\Data $helper
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Locale\ResolverInterface $resolver,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\App\ProductMetadata $productMetadata,
        \Areto\Payments\Helper\Data $helper,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->request = $request;
        $this->session = $session;
        $this->productMetadata = $productMetadata;
        $this->helper = $helper;
    }

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @api
     */
    public function initialize($paymentAction, $stateObject)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $this->getInfoInstance();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);

        // Set Initial Order Status
        $state = \Magento\Sales\Model\Order::STATE_NEW;
        $stateObject->setState($state);
        $stateObject->setStatus($state);
        $stateObject->setIsNotified(false);
    }

    /**
     * Get config payment action url
     * Used to universalize payment actions when processing payment place
     *
     * @return string
     * @api
     */
    public function getConfigPaymentAction()
    {
        $paymentAction = $this->getConfigData('payment_action');
        return empty($paymentAction) ? true : $paymentAction;
    }

    /**
     * Checkout redirect URL getter for onepage checkout (hardcode)
     *
     * @see \Magento\Checkout\Controller\Onepage::savePaymentAction()
     * @see \Magento\Quote\Model\Quote\Payment::getCheckoutRedirectUrl()
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        return $this->urlBuilder->getUrl('areto/cc/payment', ['_secure' => $this->request->isSecure()]);
    }

    /**
     * Refund specified amount for payment
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     * @api
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        parent::refund($payment, $amount);

        if ($amount <= 0) {
            throw new LocalizedException(__('Invalid amount for refund.'));
        }

        if (!$payment->getLastTransId()) {
            throw new LocalizedException(__('Invalid transaction ID.'));
        }

        // Load transaction Data
        $transactionId = $payment->getLastTransId();
        $transactionRepository = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Sales\Model\Order\Payment\Transaction\Repository');

        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $transactionRepository->getByTransactionId(
            $transactionId,
            $payment->getId(),
            $payment->getOrder()->getId()
        );

        if (!$transaction) {
            throw new LocalizedException(__('Can\'t load last transaction.'));
        }

        try {
            // Init Areto API
            $api_id = $this->getConfigData('api_id');
            $api_session = $this->getConfigData('api_session');
            $this->helper->getApi()->setEnvironment($api_id, $api_session);
            $result = $this->helper->getApi()->refund_request($transactionId, $amount, 'Refund from Magneto admin');
            if (!$result) {
                throw new \Exception('Failed to perform refund');
            }
        } catch (\Exception $e) {
            throw new LocalizedException($e->getMessage());
        }

        // Add Credit Transaction
        $payment->setAnetTransType(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND);
        $payment->setAmount($amount);

        $payment->setStatus(self::STATUS_APPROVED)
            ->setTransactionId($transactionId . '-refund')
            ->setIsTransactionClosed(1);

        return $this;
    }

    /**
     * Post request to gateway and return response
     * @param DataObject $request
     * @param ConfigInterface $config
     */
    public function postRequest(DataObject $request, ConfigInterface $config)
    {
        // Implement postRequest() method.
    }
}
