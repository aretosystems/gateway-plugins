<?php

class Areto_Payments_Block_Form_CC extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('areto/cc/form.phtml');
    }
}
