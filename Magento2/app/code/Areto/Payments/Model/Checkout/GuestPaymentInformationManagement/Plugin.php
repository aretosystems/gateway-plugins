<?php

namespace Areto\Payments\Model\Checkout\GuestPaymentInformationManagement;

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
     * @param $null
     * @param $cartId
     * @param $email
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        $null,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        if ($paymentMethod->getMethod() === \Areto\Payments\Model\Method\Cc::METHOD_CODE) {
            $additionalData = $paymentMethod->getAdditionalData();
            $this->session->setAretoCCData($additionalData);
        }
    }
}
