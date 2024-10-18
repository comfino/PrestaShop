<?php

namespace Comfino\FinancialProduct;

use Comfino\Enum;

readonly class ProductTypesListTypeEnum extends Enum
{
    public const LIST_TYPE_PAYWALL = 'paywall';
    public const LIST_TYPE_WIDGET = 'widget';

    public static function from(string $value, bool $strict = true): self
    {
        return new self($value, $strict);
    }
}
