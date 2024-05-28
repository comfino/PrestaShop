<?php

namespace Comfino\Widget;

use Comfino\Enum;

readonly class WidgetTypeEnum extends Enum
{
    public const WIDGET_SIMPLE = 'simple';
    public const WIDGET_MIXED = 'mixed';
    public const WIDGET_WITH_CALCULATOR = 'with-modal';
    public const WIDGET_WITH_EXTENDED_CALCULATOR = 'extended-modal';

    public static function from(string $value, bool $strict = true): self
    {
        return new self($value, $strict);
    }
}
