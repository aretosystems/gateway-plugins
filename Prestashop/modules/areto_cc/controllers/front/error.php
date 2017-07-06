<?php

class areto_ccErrorModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        @session_start();
        $message = $_SESSION['message'];

        $this->context->smarty->assign(array(
            'message' => $message
        ));

        $this->setTemplate('error.tpl');

        unset($_SESSION['message']);
    }
}
