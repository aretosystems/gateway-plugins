<?php

class Areto_Payments_Model_Method_CC extends Mage_Payment_Model_Method_Cc
{
    /**
     * Payment Method Code
     */
    const METHOD_CODE = 'areto_cc';

    /**
     * Payment method code
     */
    public $_code = self::METHOD_CODE;

    /**
     * Availability options
     */
    protected $_isGateway = true;
    protected $_canOrder = true;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_isInitializeNeeded = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canSaveCc = false;

    /**
     * Payment method blocks
     */
    protected $_infoBlockType = 'areto/info_CC';
    protected $_formBlockType = 'areto/form_CC';


    /**
     * Init Class
     */
    public function __construct()
    {
        $api_id = $this->getConfigData('api_id');
        $api_session = $this->getConfigData('api_session');

        Mage::helper('areto')->getApi()->setEnvironment($api_id, $api_session);
    }

    /**
     * Get initialized flag status
     * @return true
     */
    public function isInitializeNeeded()
    {
        return true;
    }

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Mage_Payment_Model_Info
     */
    public function assignData($data)
    {
        parent::assignData($data);

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();
        $info->setCcType($data->getCcType())
            ->setCcOwner($data->getCcOwner())
            ->setCcLast4(substr($data->getCcNumber(), -4))
            ->setCcNumber($data->getCcNumber())
            ->setCcCid($data->getCcCid())
            ->setCcExpMonth($data->getCcExpMonth())
            ->setCcExpYear($data->getCcExpYear())
            ->setCcSsIssue($data->getCcSsIssue())
            ->setCcSsStartMonth($data->getCcSsStartMonth())
            ->setCcSsStartYear($data->getCcSsStartYear())
        ;

        // Save in Session
        $payment = Mage::app()->getRequest()->getParam('payment');
        $this->getCheckout()->setDateOfBirth($payment['date_of_birth']);

        return $info;
    }

    /**
     * Instantiate state and set it to state object
     * @param  $paymentAction
     * @param  $stateObject
     * @return void
     */
    public function initialize($paymentAction, $stateObject)
    {
        // Set Initial Order Status
        $state = Mage_Sales_Model_Order::STATE_NEW;
        $stateObject->setState($state);
        $stateObject->setStatus($state);
        $stateObject->setIsNotified(false);

        $info = $this->getInfoInstance();

        // Save in Session
        $this->getCheckout()->setCcNumber($info->getCcNumber());
        $this->getCheckout()->setCcCid($info->getCcCid());
    }

    /**
     * Get config action to process initialization
     * @return string
     */
    public function getConfigPaymentAction()
    {
        $paymentAction = $this->getConfigData('payment_action');
        return empty($paymentAction) ? true : $paymentAction;
    }

    /**
     * Get config action to process initialization
     * @return string
     */
    //public function getConfigPaymentAction()
    //{
    //    return Mage_Payment_Model_Method_Abstract::ACTION_ORDER;
    //}

    /**
     * Check whether payment method can be used
     * @param Mage_Sales_Model_Quote $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        if (parent::isAvailable($quote) === false) {
            return false;
        }

        if (!$quote) {
            return false;
        }

        return true;
    }

    /**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  $this
     */
    public function validate()
    {
        if (parent::validate() === false) {
            return $this;
        }

        return $this;
    }

    /**
     * Get the redirect url
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('areto/payment/redirect', array('_secure' => true));
    }


    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * Refund capture
     * @param Varien_Object $payment
     * @param $amount
     * @return $this
     */
    public function refund(Varien_Object $payment, $amount)
    {
        parent::refund($payment, $amount);

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for refund.'));
        }

        if (!$payment->getLastTransId()) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid transaction ID.'));
        }

        // Load transaction Data
        $transactionId = $payment->getLastTransId();
        $transaction = $payment->getTransaction($transactionId);
        if (!$transaction) {
            Mage::throwException(Mage::helper('areto')->__('Can\'t load last transaction.'));
        }

        try {
            $result = Mage::helper('areto')->getApi()->refund_request($transactionId, $amount, 'Refund from Magneto admin');
            if (!$result) {
                throw new Exception('Failed to perform refund');
            }
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }


        // Add Credit Transaction
        $payment->setAnetTransType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND);
        $payment->setAmount($amount);

        $payment->setStatus(self::STATUS_APPROVED)
            ->setTransactionId($transactionId . '-refund')
            ->setIsTransactionClosed(1);

        return $this;
    }
}