<?php

class Areto_Payments_Block_Info_Quickpay extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();

        $this->setTemplate('areto/quickpay/info.phtml');

        // Template for Checkout page
        if ($this->getRequest()->getRequestedActionName() === 'progress') {
            $this->setTemplate('areto/quickpay/title.phtml');
        }

    }

    /**
     * Returns code of payment method
     *
     * @return string
     */
    public function getMethodCode()
    {
        return $this->getInfo()->getMethodInstance()->getCode();
    }
}
