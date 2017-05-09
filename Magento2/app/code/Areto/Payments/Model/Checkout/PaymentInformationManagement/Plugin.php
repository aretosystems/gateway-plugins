<?php

namespace Areto\Payments\Model\Checkout\PaymentInformationManagement;

class Plugin
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @param \Magento\Checkout\Model\Session $session
     */
    public function __construct(
        \Magento\Checkout\Model\Session $session
    )
    {
        $this->session = $session;
    }

    /**
     * Save Additional data to session
     * @param \Magento\Checkout\Model\PaymentInformationManagement $subject
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface $billingAddress
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        \Magento\Checkout\Model\PaymentInformationManagement $subject,
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress
    )
    {
        if ($paymentMethod->getMethod() === \Areto\Payments\Model\Method\Cc::METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $this->session->setAretoCCData($additionalData);
        }
    }
}
