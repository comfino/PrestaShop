<?php

namespace Comfino\Api\Dto\Payment;

class LoanParameters
{
    /** @var int
     * @readonly */
    public $instalmentAmount;
    /** @var int
     * @readonly */
    public $toPay;
    /** @var int
     * @readonly */
    public $loanTerm;
    /** @var float
     * @readonly */
    public $rrso;
    /** @var int|null
     * @readonly */
    public $initialPaymentValue;
    /** @var float|null
     * @readonly */
    public $initialPaymentRate;
    /** @var int|null
     * @readonly */
    public $redemptionPaymentValue;
    /** @var float|null
     * @readonly */
    public $redemptionPaymentRate;
    /** @var float|null
     * @readonly */
    public $interest;

    /**
     * @param int $instalmentAmount
     * @param int $toPay
     * @param int $loanTerm
     * @param float $rrso
     */
    public function __construct(
        int $instalmentAmount,
        int $toPay,
        int $loanTerm,
        float $rrso,
        ?int $initialPaymentValue = null,
        ?float $initialPaymentRate = null,
        ?int $redemptionPaymentValue = null,
        ?float $redemptionPaymentRate = null,
        ?float $interest = null
    )
    {
        $this->instalmentAmount = $instalmentAmount;
        $this->toPay = $toPay;
        $this->loanTerm = $loanTerm;
        $this->rrso = $rrso;
    }
}
