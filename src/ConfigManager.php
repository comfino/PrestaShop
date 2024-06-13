<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Comfino;

use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\StorageAdapter;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ConfigManager
{
    public const CONFIG_OPTIONS = [
        'payment_settings' => [
            'COMFINO_API_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_PAYMENT_TEXT' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_MINIMAL_CART_AMOUNT' => ConfigurationManager::OPT_VALUE_TYPE_FLOAT,
        ],
        'sale_settings' => [
            'COMFINO_PRODUCT_CATEGORY_FILTERS' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
        ],
        'widget_settings' => [
            'COMFINO_WIDGET_ENABLED' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_WIDGET_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_TARGET_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_WIDGET_TYPE' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_OFFER_TYPE' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_EMBED_METHOD' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_CODE' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
        ],
        'developer_settings' => [
            'COMFINO_IS_SANDBOX' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_SANDBOX_API_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
        ],
        'hidden_settings' => [
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_IGNORED_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_FORBIDDEN_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_STATUS_MAP' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
        ],
    ];

    public const ACCESSIBLE_CONFIG_OPTIONS = [
        'COMFINO_PAYMENT_TEXT',
        'COMFINO_MINIMAL_CART_AMOUNT',
        'COMFINO_IS_SANDBOX',
        'COMFINO_PRODUCT_CATEGORY_FILTERS',
        'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES',
        'COMFINO_WIDGET_ENABLED',
        'COMFINO_WIDGET_KEY',
        'COMFINO_WIDGET_PRICE_SELECTOR',
        'COMFINO_WIDGET_TARGET_SELECTOR',
        'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
        'COMFINO_WIDGET_TYPE',
        'COMFINO_WIDGET_OFFER_TYPE',
        'COMFINO_WIDGET_EMBED_METHOD',
        'COMFINO_WIDGET_CODE',
        'COMFINO_WIDGET_PROD_SCRIPT_VERSION',
        'COMFINO_WIDGET_DEV_SCRIPT_VERSION',
        'COMFINO_IGNORED_STATUSES',
        'COMFINO_FORBIDDEN_STATUSES',
        'COMFINO_STATUS_MAP',
    ];

    /** @var ConfigurationManager */
    private static $configuration_manager;

    public static function getInstance(): ConfigurationManager
    {
        if (self::$configuration_manager === null) {
            self::$configuration_manager = ConfigurationManager::getInstance(
                array_merge(array_merge(...array_values(self::CONFIG_OPTIONS))),
                self::ACCESSIBLE_CONFIG_OPTIONS,
                new StorageAdapter(),
                new JsonSerializer()
            );
        }

        return self::$configuration_manager;
    }

    public static function load(): array
    {
        $configuration = [];

        foreach (array_merge(array_merge(...array_values(self::CONFIG_OPTIONS))) as $opt_name => $opt_type_flags) {
            $configuration[$opt_name] = \Configuration::get($opt_name, null, null, null, null);
        }

        return $configuration;
    }

    public static function save(array $configuration): void
    {
        foreach ($configuration as $opt_name => $opt_value) {
            \Configuration::updateValue($opt_name, $opt_value);
        }
    }

    /**
     * @param string[]|null $selected_env_fields
     * @return string[]
     */
    public static function getEnvironmentInfo(?array $selected_env_fields = null): array
    {
        $env_fields = [
            'plugin_version' => COMFINO_VERSION,
            'shop_version' => _PS_VERSION_,
            'symfony_version' => COMFINO_PS_17 && class_exists('\Symfony\Component\HttpKernel\Kernel')
                ? \Symfony\Component\HttpKernel\Kernel::VERSION
                : 'n/a',
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'],
            'server_name' => $_SERVER['SERVER_NAME'],
            'server_addr' => $_SERVER['SERVER_ADDR'],
            'database_version' => \Db::getInstance()->getVersion(),
        ];

        if (empty($selected_env_fields)) {
            return $env_fields;
        }

        $filtered_env_fields = [];

        foreach ($selected_env_fields as $env_field) {
            if (array_key_exists($env_field, $env_fields)) {
                $filtered_env_fields[$env_field] = $env_fields[$env_field];
            }
        }

        return $filtered_env_fields;
    }

    /**
     * @return string[]
     */
    public static function getAllProductCategories(): ?array
    {
        static $categories = null;

        if ($categories === null) {
            $categories = [];

            foreach (\Category::getSimpleCategories(\Context::getContext()->language->id) as $category) {
                $categories[$category['id_category']] = $category['name'];
            }
        }

        return $categories;
    }

    public static function getConfigurationValue(string $optionName)
    {
        return self::getInstance()->getConfigurationValue($optionName);
    }

    public static function isSandboxMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_IS_SANDBOX');
    }

    public static function isWidgetEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_WIDGET_ENABLED');
    }

    public static function getApiKey(): ?string
    {
        return self::isSandboxMode()
            ? self::getInstance()->getConfigurationValue('COMFINO_SANDBOX_API_KEY')
            : self::getInstance()->getConfigurationValue('COMFINO_API_KEY');
    }

    public static function getWidgetKey(): ?string
    {
        return self::getInstance()->getConfigurationValue('COMFINO_WIDGET_KEY');
    }

    /**
     * @return string[]
     */
    public static function getIgnoredStatuses(): array
    {
        return self::getConfigurationValue('COMFINO_IGNORED_STATUSES') ?? StatusManager::DEFAULT_IGNORED_STATUSES;
    }

    /**
     * @return string[]
     */
    public static function getForbiddenStatuses(): array
    {
        return self::getConfigurationValue('COMFINO_FORBIDDEN_STATUSES') ?? StatusManager::DEFAULT_FORBIDDEN_STATUSES;
    }

    /**
     * @return string[]
     */
    public static function getStatusMap(): array
    {
        return self::getConfigurationValue('COMFINO_STATUS_MAP') ?? ShopStatusManager::DEFAULT_STATUS_MAP;
    }

    public static function updateConfiguration($configuration_options, $only_accessible_options = true): void
    {
        if ($only_accessible_options) {
            self::getInstance()->updateConfigurationOptions($configuration_options);
        } else {
            self::getInstance()->setConfigurationValues($configuration_options);
        }

        self::getInstance()->persist();
    }

    public static function deleteConfigurationValues(): bool
    {
        $result = true;

        foreach (self::CONFIG_OPTIONS as $options) {
            foreach ($options as $option_name) {
                $result &= \Configuration::deleteByName($option_name);
            }
        }

        return $result;
    }

    public static function updateWidgetCode(\PaymentModule $module, string $last_widget_code_hash): void
    {
        ErrorLogger::init($module);

        try {
            $initial_widget_code = self::getInitialWidgetCode();
            $current_widget_code = self::getCurrentWidgetCode($module);

            if (md5($current_widget_code) === $last_widget_code_hash) {
                // Widget code not changed since last installed version - safely replace with new one.
                \Configuration::updateValue('COMFINO_WIDGET_CODE', $initial_widget_code);
            }
        } catch (\Throwable $e) {
            ErrorLogger::sendError(
                'Widget code update',
                $e->getCode(),
                $e->getMessage(),
                null,
                null,
                null,
                $e->getTraceAsString()
            );
        }
    }

    /**
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public static function getCurrentWidgetCode(\PaymentModule $module, $product_id = null): string
    {
        $widget_code = trim(str_replace("\r", '', \Configuration::get('COMFINO_WIDGET_CODE')));
        $product_data = self::getProductData($module, $product_id);

        $options_to_inject = [];

        if (strpos($widget_code, 'productId') === false) {
            $options_to_inject[] = "        productId: $product_data[product_id]";
        }
        if (strpos($widget_code, 'availOffersUrl') === false) {
            $options_to_inject[] = "        availOffersUrl: '$product_data[avail_offers_url]'";
        }

        if (count($options_to_inject)) {
            $injected_init_options = implode(",\n", $options_to_inject) . ",\n";

            return preg_replace('/\{\n(.*widgetKey:)/', "{\n$injected_init_options\$1", $widget_code);
        }

        return $widget_code;
    }

    /**
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public static function getWidgetVariables(\PaymentModule $module, $product_id = null): array
    {
        $product_data = self::getProductData($module, $product_id);

        return [
            '{WIDGET_SCRIPT_URL}' => ApiClient::getWidgetScriptUrl(),
            '{PRODUCT_ID}' => $product_data['product_id'],
            '{PRODUCT_PRICE}' => $product_data['price'],
            '{PLATFORM}' => 'prestashop',
            '{PLATFORM_VERSION}' => _PS_VERSION_,
            '{PLATFORM_DOMAIN}' => \Tools::getShopDomain(),
            '{PLUGIN_VERSION}' => COMFINO_VERSION,
            '{AVAILABLE_OFFER_TYPES}' => $product_data['avail_offers_url'],
        ];
    }

    public static function getConfigurationValues(string $options_group, array $options_to_return = []): array
    {
        if (!array_key_exists($options_group, self::CONFIG_OPTIONS)) {
            return [];
        }

        return count($options_to_return)
            ? self::getInstance()->getConfigurationValues($options_to_return)
            : self::getInstance()->getConfigurationValues(self::CONFIG_OPTIONS[$options_group]);
    }

    public static function initConfigurationValues(): void
    {
        if (\Configuration::hasKey('COMFINO_API_KEY')) {
            // Avoid overwriting of existing configuration if plugin is reinstalled/upgraded.
            return;
        }

        $initial_config_values = [
            'COMFINO_PAYMENT_TEXT' => '(Raty | Kup Teraz, Zapłać Później | Finansowanie dla Firm)',
            'COMFINO_MINIMAL_CART_AMOUNT' => 30,
            'COMFINO_PRODUCT_CATEGORY_FILTERS' => '',
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => 'INSTALLMENTS_ZERO_PERCENT,PAY_LATER',
            'COMFINO_WIDGET_ENABLED' => false,
            'COMFINO_WIDGET_KEY' => '',
            'COMFINO_WIDGET_PRICE_SELECTOR' => COMFINO_PS_17 ? 'span.current-price-value' : 'span[itemprop=price]',
            'COMFINO_WIDGET_TARGET_SELECTOR' => 'div.product-actions',
            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => '',
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 0,
            'COMFINO_WIDGET_TYPE' => 'with-modal',
            'COMFINO_WIDGET_OFFER_TYPE' => 'CONVENIENT_INSTALLMENTS',
            'COMFINO_WIDGET_EMBED_METHOD' => 'INSERT_INTO_LAST',
            'COMFINO_WIDGET_CODE' => self::getInitialWidgetCode(),
            'COMFINO_WIDGET_PROD_SCRIPT_VERSION' => '',
            'COMFINO_WIDGET_DEV_SCRIPT_VERSION' => '',
            'COMFINO_IGNORED_STATUSES' => implode(',', StatusManager::DEFAULT_IGNORED_STATUSES),
            'COMFINO_FORBIDDEN_STATUSES' => implode(',', StatusManager::DEFAULT_FORBIDDEN_STATUSES),
            'COMFINO_STATUS_MAP' => json_encode(ShopStatusManager::DEFAULT_STATUS_MAP),
        ];

        foreach ($initial_config_values as $opt_name => $opt_value) {
            \Configuration::updateValue($opt_name, $opt_value);
        }
    }

    /**
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    private static function getProductData(\PaymentModule $module, $product_id): array
    {
        $context = \Context::getContext();
        $avail_offers_url = ApiService::getControllerUrl($module, 'availableoffertypes', [], false);

        $price = 'null';

        if ($product_id !== null) {
            $avail_offers_url .= ((strpos($avail_offers_url, '?') === false ? '?' : '&') . "product_id=$product_id");

            if (($price = \Product::getPriceStatic($product_id)) === null) {
                $price = 'null';
            } else {
                $price = (new Tools($context))->getFormattedPrice($price);
            }
        } else {
            $product_id = 'null';
        }

        return [
            'product_id' => $product_id,
            'price' => $price,
            'avail_offers_url' => $avail_offers_url,
        ];
    }

    private static function getInitialWidgetCode(): string
    {
        return trim("
var script = document.createElement('script');
script.onload = function () {
    ComfinoProductWidget.init({
        widgetKey: '{WIDGET_KEY}',
        priceSelector: '{WIDGET_PRICE_SELECTOR}',
        widgetTargetSelector: '{WIDGET_TARGET_SELECTOR}',
        priceObserverSelector: '{WIDGET_PRICE_OBSERVER_SELECTOR}',
        priceObserverLevel: {WIDGET_PRICE_OBSERVER_LEVEL},
        type: '{WIDGET_TYPE}',
        offerType: '{OFFER_TYPE}',
        embedMethod: '{EMBED_METHOD}',
        numOfInstallments: 0,
        price: null,
        productId: {PRODUCT_ID},
        productPrice: {PRODUCT_PRICE},
        platform: '{PLATFORM}',
        platformVersion: '{PLATFORM_VERSION}',
        platformDomain: '{PLATFORM_DOMAIN}',
        pluginVersion: '{PLUGIN_VERSION}',
        availOffersUrl: '{AVAILABLE_OFFER_TYPES}',
        callbackBefore: function () {},
        callbackAfter: function () {},
        onOfferRendered: function (jsonResponse, widgetTarget, widgetNode) { },
        onGetPriceElement: function (priceSelector, priceObserverSelector) { return null; },
        debugMode: window.location.hash && window.location.hash.substring(1) === 'comfino_debug'
    });
};
script.src = '{WIDGET_SCRIPT_URL}';
script.async = true;
document.getElementsByTagName('head')[0].appendChild(script);
");
    }
}
