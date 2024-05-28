<?php

namespace Comfino\Api\Dto\Payment;

readonly class FinancialProduct
{
    /** @var string */
    public string $name;
    /** @var LoanTypeEnum */
    public LoanTypeEnum $type;
    /** @var string */
    public string $description;
    /** @var string */
    public string $icon;
    /** @var int */
    public int $instalmentAmount;
    /** @var int */
    public int $toPay;
    /** @var int */
    public int $loanTerm;
    /** @var float */
    public float $rrso;
    /** @var string */
    public string $representativeExample;
    /** @var string|null */
    public ?string $remarks;
    /** @var LoanParameters[] */
    public array $loanParameters;

    /**
     * @param string $name
     * @param LoanTypeEnum $type
     * @param string $description
     * @param string $icon
     * @param int $instalmentAmount
     * @param int $toPay
     * @param int $loanTerm
     * @param float $rrso
     * @param string $representativeExample
     * @param string|null $remarks
     * @param LoanParameters[] $loanParameters
     */
    public function __construct(
        string $name,
        LoanTypeEnum $type,
        string $description,
        string $icon,
        int $instalmentAmount,
        int $toPay,
        int $loanTerm,
        float $rrso,
        string $representativeExample,
        ?string $remarks,
        array $loanParameters
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->icon = $icon;
        $this->instalmentAmount = $instalmentAmount;
        $this->toPay = $toPay;
        $this->loanTerm = $loanTerm;
        $this->rrso = $rrso;
        $this->representativeExample = $representativeExample;
        $this->remarks = $remarks;
        $this->loanParameters = $loanParameters;
    }
}
