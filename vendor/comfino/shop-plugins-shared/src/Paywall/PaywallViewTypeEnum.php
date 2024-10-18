<?php

namespace Comfino\Paywall;

use Comfino\Enum;

readonly class PaywallViewTypeEnum extends Enum
{
    public const PAYWALL_VIEW_FULL = 'full';
    public const PAYWALL_VIEW_LIST = 'list';

    public static function from(string $value, bool $strict = true): self
    {
        return new self($value, $strict);
    }
}
