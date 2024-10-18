<?php

namespace Comfino\Common\Backend\Cache;

use Comfino\Enum;

readonly class ItemTypeEnum extends Enum
{
    public const PAYWALL_TEMPLATE = 'paywall_template';
    public const PAYWALL_STYLE = 'paywall_style';
    public const PAYWALL_SCRIPT = 'paywall_script';
    public const PAYWALL_FRONTEND_STYLE = 'paywall_frontend_style';
    public const PAYWALL_FRONTEND_SCRIPT = 'paywall_frontend_script';
    public const ADMIN_PRODUCT_TYPES = 'admin_product_types';
    public const ADMIN_WIDGET_TYPES = 'admin_widget_types';

    public static function from(string $value, bool $strict = true): self
    {
        return new self($value, $strict);
    }
}
