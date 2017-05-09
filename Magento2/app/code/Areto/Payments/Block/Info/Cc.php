<?php

namespace Areto\Payments\Block\Info;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Api\TransactionRepositoryInterface;

class Cc extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_template = 'Areto_Payments::info/cc.phtml';

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * Constructor
     *
     * @param TransactionRepositoryInterface $transactionRepository
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        Template\Context $context,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->transactionRepository = $transactionRepository;
    }
}


