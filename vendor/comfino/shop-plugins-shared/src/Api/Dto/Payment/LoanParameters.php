<?php

namespace Comfino\Api\Dto\Payment;

readonly class LoanParameters
{
    /** @var int */
    public int $instalmentAmount;
    /** @var int */
    public int $toPay;
    /** @var int */
    public int $loanTerm;
    /** @var float */
    public float $rrso;

    /**
     * @param int $instalmentAmount
     * @param int $toPay
     * @param int $loanTerm
     * @param float $rrso
     */
    public function __construct(int $instalmentAmount, int $toPay, int $loanTerm, float $rrso)
    {
        $this->instalmentAmount = $instalmentAmount;
        $this->toPay = $toPay;
        $this->loanTerm = $loanTerm;
        $this->rrso = $rrso;
    }
}
