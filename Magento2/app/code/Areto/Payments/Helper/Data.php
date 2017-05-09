<?php

namespace Areto\Payments\Helper;

if (!class_exists('\Areto', false)) {
    require_once dirname(__FILE__) . '/../vendor/Areto.php';
}

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Sales\Model\Order\Payment\Transaction;


/**
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class Data extends AbstractHelper
{
    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    /**
     * @var \Magento\Payment\Model\Config
     */
    protected $_config;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    /**
     * @var Areto
     */
    protected $_api;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory
     */
    protected $orderStatusCollectionFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Tax\Helper\Data
     */
    protected $taxHelper;

    /**
     * @var \Magento\Framework\App\ProductMetadata
     */
    protected $productMetadata;

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Encryption\EncryptorInterface $encryptor
     * @param \Magento\Payment\Model\Config $config
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
     * @param \Magento\Tax\Helper\Data $taxHelper
     * @param \Magento\Framework\App\ProductMetadata $productMetadata
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Payment\Model\Config $config,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory $orderStatusCollectionFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Tax\Helper\Data $taxHelper,
        \Magento\Framework\App\ProductMetadata $productMetadata
    )
    {
        parent::__construct($context);
        $this->_encryptor = $encryptor;
        $this->_config = $config;
        $this->_moduleList = $moduleList;
        $this->_orderConfig = $orderConfig;

        $this->orderStatusCollectionFactory = $orderStatusCollectionFactory;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;

        $this->taxHelper = $taxHelper;
        $this->productMetadata = $productMetadata;
    }


    /**
     * Retrieve information from payment configuration
     * @param $field
     * @param $paymentMethodCode
     * @param $storeId
     * @param bool|false $flag
     * @return bool|mixed
     */
    public function getConfigData($field, $paymentMethodCode, $storeId, $flag = false)
    {
        $path = 'payment/' . $paymentMethodCode . '/' . $field;

        if (!$flag) {
            return $this->scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        } else {
            return $this->scopeConfig->isSetFlag($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        }
    }

    /**
     * Get Store
     * @param int|string|null|bool|\Magento\Store\Api\Data\StoreInterface $id [optional]
     * @return \Magento\Store\Api\Data\StoreInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getStore($id = null)
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Store\Model\StoreManagerInterface $manager */
        $manager = $om->get('Magento\Store\Model\StoreManagerInterface');
        return $manager->getStore($id);
    }

    /**
     * Get Visitor IP address
     * @return string
     */
    public function getRemoteAddr()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $ra */
        $ra = $om->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');

        return $ra->getRemoteAddress();
    }

    /**
     * Get Api Handler
     * @return \Areto
     */
    public function getApi()
    {
        if (!$this->_api) {
            $this->_api = new \Areto();
        }

        return $this->_api;
    }

    /**
     * Get Assigned State
     * @param $status
     * @return \Magento\Framework\DataObject
     */
    public function getAssignedState($status)
    {

        $collection = $this->orderStatusCollectionFactory->create()->joinStates();
        $status = $collection->addAttributeToFilter('main_table.status', $status)->getFirstItem();
        return $status;
    }

    /**
     * Create Invoice
     * @param \Magento\Sales\Model\Order $order
     * @param array $qtys
     * @param bool $online
     * @param string $comment
     * @return \Magento\Sales\Model\Order\Invoice
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function makeInvoice(\Magento\Sales\Model\Order $order, array $qtys = [], $online = false, $comment = '')
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $this->invoiceService->prepareInvoice($order, $qtys);
        $invoice->setRequestedCaptureCase($online ? \Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE : \Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);

        // Add Comment
        if (!empty($comment)) {
            $invoice->addComment(
                $comment,
                true,
                true
            );

            $invoice->setCustomerNote($comment);
            $invoice->setCustomerNoteNotify(true);
        }

        $invoice->register();
        $invoice->getOrder()->setIsInProcess(true);

        /** @var \Magento\Framework\DB\Transaction $transactionSave */
        $transactionSave = $om->create(
            'Magento\Framework\DB\Transaction'
        )
            ->addObject($invoice)
            ->addObject($invoice->getOrder());
        $transactionSave->save();

        // send invoice emails
        try {
            $this->invoiceSender->send($invoice);
        } catch (\Exception $e) {
            $om->get('Psr\Log\LoggerInterface')->critical($e);
        }

        $invoice->setIsPaid(true);

        // Assign Last Transaction Id with Invoice
        $transactionId = $invoice->getOrder()->getPayment()->getLastTransId();
        if ($transactionId) {
            $invoice->setTransactionId($transactionId);
            $invoice->save();
        }

        return $invoice;
    }
}
