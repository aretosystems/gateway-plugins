<?php

class Areto_Payments_Block_Form_Quickpay extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('areto/quickpay/form.phtml');
    }
}
