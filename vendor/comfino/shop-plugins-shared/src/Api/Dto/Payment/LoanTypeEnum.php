<?php

namespace Comfino\Api\Dto\Payment;

use Comfino\Enum;

readonly class LoanTypeEnum extends Enum
{
    public const INSTALLMENTS_ZERO_PERCENT = 'INSTALLMENTS_ZERO_PERCENT';
    public const CONVENIENT_INSTALLMENTS = 'CONVENIENT_INSTALLMENTS';
    public const PAY_LATER = 'PAY_LATER';
    public const COMPANY_INSTALLMENTS = 'COMPANY_INSTALLMENTS';
    public const COMPANY_BNPL = 'COMPANY_BNPL';
    public const RENEWABLE_LIMIT = 'RENEWABLE_LIMIT';
    public const BLIK = 'BLIK';

    public static function from(string $value, bool $strict = true): self
    {
        return new self($value, $strict);
    }
}
