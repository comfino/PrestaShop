<?php

declare(strict_types=1);

namespace Comfino\Common\Frontend;

use InvalidArgumentException;

class WidgetSdkInitScriptHelper
{
    public const WIDGET_INIT_PARAMS = [
        'WIDGET_KEY',
        'WIDGET_TARGET_SELECTOR',
        'WIDGET_PRICE_SELECTOR',
        'WIDGET_PRICE_OBSERVER_SELECTOR',
        'WIDGET_PRICE_OBSERVER_LEVEL',
        'WIDGET_TYPE',
        'OFFER_TYPES',
        'EMBED_METHOD',
        'ENVIRONMENT',
        'SHOW_PROVIDER_LOGOS',
        'HAS_PRICE_INPUT',
        'CUSTOM_BANNER_CSS_URL',
        'CUSTOM_CALCULATOR_CSS_URL',
    ];

    public const WIDGET_INIT_VARIABLES = [
        'WIDGET_SCRIPT_URL',
        'PRODUCT_ID',
        'PRODUCT_PRICE',
        'AVAILABLE_PRODUCT_TYPES',
        'PRODUCT_CART_DETAILS',
        'LANGUAGE',
        'CURRENCY',
        'SHOP_ENVIRONMENT',
        'LOGGING_TOKEN',
        'TRACK_ID',
    ];

    /**
     * @throws InvalidArgumentException
     * @param string $widgetInitCode
     * @param mixed[] $widgetInitParams
     * @param mixed[] $widgetInitVariables
     */
    public static function renderWidgetInitScript($widgetInitCode, $widgetInitParams, $widgetInitVariables): string
    {
        $widgetInitParamsAssocKeys = array_flip(self::WIDGET_INIT_PARAMS);
        $widgetInitVariablesAssocKeys = array_flip(self::WIDGET_INIT_VARIABLES);

        if (count(array_intersect_key($widgetInitParamsAssocKeys, $widgetInitParams)) !== count(self::WIDGET_INIT_PARAMS)) {
            throw new InvalidArgumentException('Invalid widget initialization parameters.');
        }

        if (count(array_intersect_key($widgetInitVariablesAssocKeys, $widgetInitVariables)) !== count(self::WIDGET_INIT_VARIABLES)) {
            throw new InvalidArgumentException('Invalid widget initialization variables.');
        }

        $serializeValue = static function ($varValue): string {
            if ($varValue === null) {
                return 'null';
            }

            if (is_bool($varValue)) {
                return $varValue ? 'true' : 'false';
            }

            if (is_int($varValue) || is_float($varValue) || is_numeric($varValue)) {
                return (string) $varValue;
            }

            $result = json_encode($varValue, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);

            return $result !== false ? $result : 'null';
        };

        return str_replace(
            array_merge(
                array_map(
                    static function (string $widgetInitParamName) : string {
                        return '{' . $widgetInitParamName . '}';
                    },
                    array_merge(self::WIDGET_INIT_PARAMS, array_keys($widgetInitVariables))
                ),
                ["'true'", "'false'", "'null'"]
            ),
            array_merge(
                array_map(
                    $serializeValue,
                    array_merge(
                        array_merge($widgetInitParamsAssocKeys, $widgetInitParams),
                        array_values($widgetInitVariables)
                    )
                ),
                ['true', 'false', 'null']
            ),
            $widgetInitCode
        );
    }

    /**
     * @param string $widgetInitCode
     */
    public static function initScriptRequiresUpdate($widgetInitCode): bool
    {
        return hash('sha256', $widgetInitCode) !== hash('sha256', self::getInitialWidgetCode());
    }

    public static function getInitialWidgetCodeHash(): string
    {
        return hash('sha256', self::getInitialWidgetCode());
    }

    public static function getInitialWidgetCode(): string
    {
        return include 'WidgetSdkInitScript.php';
    }
}
