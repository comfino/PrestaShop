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

namespace Comfino\Configuration;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\CategoryTree\BuildStrategy;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Common\Frontend\WidgetInitScriptHelper;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\Product\CategoryTree;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\Order\ShopStatusManager;
use Comfino\Tools;

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
            'COMFINO_DEBUG' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_SERVICE_MODE' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
        ],
        'hidden_settings' => [
            'COMFINO_WIDGET_PROD_SCRIPT_VERSION' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_DEV_SCRIPT_VERSION' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_IGNORED_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_FORBIDDEN_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_STATUS_MAP' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
            'COMFINO_JS_PROD_PATH' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_CSS_PROD_PATH' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_JS_DEV_PATH' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_CSS_DEV_PATH' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_API_CONNECT_TIMEOUT' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_API_TIMEOUT' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_API_CONNECT_NUM_ATTEMPTS' => ConfigurationManager::OPT_VALUE_TYPE_INT,
        ],
    ];

    public const ACCESSIBLE_CONFIG_OPTIONS = [
        'COMFINO_PAYMENT_TEXT',
        'COMFINO_MINIMAL_CART_AMOUNT',
        'COMFINO_IS_SANDBOX',
        'COMFINO_DEBUG',
        'COMFINO_SERVICE_MODE',
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
        'COMFINO_JS_PROD_PATH',
        'COMFINO_CSS_PROD_PATH',
        'COMFINO_JS_DEV_PATH',
        'COMFINO_CSS_DEV_PATH',
        'COMFINO_API_CONNECT_TIMEOUT',
        'COMFINO_API_TIMEOUT',
        'COMFINO_API_CONNECT_NUM_ATTEMPTS',
    ];

    /** @var ConfigurationManager */
    private static $configurationManager;
    /** @var int[] */
    private static $availConfigOptions;

    public static function getInstance(): ConfigurationManager
    {
        if (self::$configurationManager === null) {
            self::$configurationManager = ConfigurationManager::getInstance(
                self::getAvailableConfigOptions(),
                self::ACCESSIBLE_CONFIG_OPTIONS,
                new StorageAdapter(),
                new JsonSerializer()
            );
        }

        return self::$configurationManager;
    }

    public static function load(): array
    {
        $configuration = [];

        foreach (self::getAvailableConfigOptions() as $optName => $optTypeFlags) {
            $configuration[$optName] = \Configuration::get($optName, null, null, null, null);
        }

        return $configuration;
    }

    public static function save(array $configuration): void
    {
        foreach ($configuration as $optName => $optValue) {
            \Configuration::updateValue($optName, $optValue);
        }
    }

    /**
     * @param string[]|null $selectedEnvFields
     * @return string[]
     */
    public static function getEnvironmentInfo(?array $selectedEnvFields = null): array
    {
        $envFields = [
            'plugin_version' => COMFINO_VERSION,
            'plugin_build_ts' => COMFINO_BUILD_TS,
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

        if (empty($selectedEnvFields)) {
            return $envFields;
        }

        $filteredEnvFields = [];

        foreach ($selectedEnvFields as $envField) {
            if (array_key_exists($envField, $envFields)) {
                $filteredEnvFields[$envField] = $envFields[$envField];
            }
        }

        return $filteredEnvFields;
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

    public static function getCategoriesTree(): CategoryTree
    {
        /** @var CategoryTree $categoriesTree */
        static $categoriesTree = null;

        if ($categoriesTree === null) {
            $categoriesTree = new CategoryTree(new BuildStrategy());
        }

        return $categoriesTree;
    }

    public static function getConfigurationValue(string $optionName, $defaultValue = null)
    {
        return self::getInstance()->getConfigurationValue($optionName) ?? $defaultValue;
    }

    public static function getConfigurationValueType(string $optionName): int
    {
        return self::getAvailableConfigOptions()[$optionName] ?? ConfigurationManager::OPT_VALUE_TYPE_STRING;
    }

    public static function isSandboxMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_IS_SANDBOX') ?? false;
    }

    public static function isWidgetEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_WIDGET_ENABLED') ?? false;
    }

    public static function isDebugMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_DEBUG') ?? false;
    }

    public static function isServiceMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_SERVICE_MODE') ?? false;
    }

    public static function isDevEnv(): bool
    {
        return ((string) getenv('COMFINO_DEV')) === ('PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN'));
    }

    public static function useUnminifiedScripts(): bool
    {
        return getenv('COMFINO_DEV_USE_UNMINIFIED_SCRIPTS') === 'TRUE';
    }

    public static function getLogoApiHost(): string
    {
        return self::getApiHost(ApiClient::getInstance()->getApiHost());
    }

    public static function getLogoUrl(): string
    {
        return self::getLogoApiHost() . '/v1/get-logo-url?auth='
            . FrontendHelper::getLogoAuthHash('PS', _PS_VERSION_, COMFINO_VERSION, COMFINO_BUILD_TS);
    }

    public static function getPaywallLogoUrl(): string
    {
        return self::getLogoApiHost() . '/v1/get-paywall-logo?auth='
            . FrontendHelper::getPaywallLogoAuthHash(
                'PS',
                _PS_VERSION_,
                COMFINO_VERSION,
                ApiClient::getInstance()->getApiKey(),
                self::getWidgetKey(),
                COMFINO_BUILD_TS
            );
    }

    public static function getApiHost(?string $apiHost = null): ?string
    {
        if (self::isDevEnv() && getenv('COMFINO_DEV_API_HOST')) {
            return getenv('COMFINO_DEV_API_HOST');
        }

        return $apiHost;
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
        if (!is_array($ignoredStatuses = self::getConfigurationValue('COMFINO_IGNORED_STATUSES'))) {
            $ignoredStatuses = null;
        }

        return $ignoredStatuses ?? StatusManager::DEFAULT_IGNORED_STATUSES;
    }

    /**
     * @return string[]
     */
    public static function getForbiddenStatuses(): array
    {
        if (!is_array($forbiddenStatuses = self::getConfigurationValue('COMFINO_FORBIDDEN_STATUSES'))) {
            $forbiddenStatuses = null;
        }

        return $forbiddenStatuses ?? StatusManager::DEFAULT_FORBIDDEN_STATUSES;
    }

    /**
     * @return string[]
     */
    public static function getStatusMap(): array
    {
        if (!is_array($statusMap = self::getConfigurationValue('COMFINO_STATUS_MAP'))) {
            $statusMap = null;
        }

        return $statusMap ?? ShopStatusManager::DEFAULT_STATUS_MAP;
    }

    public static function updateConfigurationValue(string $optionName, $optionValue): void
    {
        self::getInstance()->setConfigurationValue($optionName, $optionValue);
        self::getInstance()->persist();
    }

    public static function updateConfiguration($configurationOptions, $onlyAccessibleOptions = true): void
    {
        if ($onlyAccessibleOptions) {
            self::getInstance()->updateConfigurationOptions($configurationOptions);
        } else {
            self::getInstance()->setConfigurationValues($configurationOptions);
        }

        self::getInstance()->persist();
    }

    public static function deleteConfigurationValues(?array $configurationOptions = null): bool
    {
        $result = true;

        if ($configurationOptions !== null) {
            foreach ($configurationOptions as $optionName) {
                $result &= \Configuration::deleteByName($optionName);
            }
        } else {
            foreach (self::CONFIG_OPTIONS as $options) {
                foreach ($options as $optionName) {
                    $result &= \Configuration::deleteByName($optionName);
                }
            }
        }

        return $result;
    }

    public static function updateWidgetCode(?string $lastWidgetCodeHash = null): void
    {
        ErrorLogger::init();

        try {
            $initialWidgetCode = WidgetInitScriptHelper::getInitialWidgetCode();
            $currentWidgetCode = self::getCurrentWidgetCode();

            if ($lastWidgetCodeHash === null || md5($currentWidgetCode) === $lastWidgetCodeHash) {
                // Widget code not changed since last installed version - safely replace with new one.
                self::updateConfigurationValue('COMFINO_WIDGET_CODE', $initialWidgetCode);
            }
        } catch (\Throwable $e) {
            ErrorLogger::sendError(
                $e,
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
    public static function getCurrentWidgetCode(?int $productId = null): string
    {
        $widgetCode = trim(str_replace("\r", '', \Configuration::get('COMFINO_WIDGET_CODE')));
        $productData = self::getProductData($productId);

        $optionsToInject = [];

        if (strpos($widgetCode, 'productId') === false) {
            $optionsToInject[] = "        productId: $productData[product_id]";
        }
        if (strpos($widgetCode, 'availOffersUrl') === false) {
            $optionsToInject[] = "        availOffersUrl: '$productData[avail_offers_url]'";
        }

        if (count($optionsToInject) > 0) {
            $injectedInitOptions = implode(",\n", $optionsToInject) . ",\n";

            return preg_replace('/\{\n(.*widgetKey:)/', "{\n$injectedInitOptions\$1", $widgetCode);
        }

        return $widgetCode;
    }

    public static function getWidgetScriptUrl(): string
    {
        if (self::isDevEnv() && getenv('COMFINO_DEV_WIDGET_SCRIPT_URL')) {
            return getenv('COMFINO_DEV_WIDGET_SCRIPT_URL');
        }

        $widgetScriptUrl = self::isSandboxMode() ? 'https://widget.craty.pl' : 'https://widget.comfino.pl';
        $widgetProdScriptVersion = self::getConfigurationValue('COMFINO_WIDGET_PROD_SCRIPT_VERSION');

        if (empty($widgetProdScriptVersion)) {
            $widgetScriptUrl .= '/comfino.min.js';
        } else {
            $widgetScriptUrl .= ('/' . trim($widgetProdScriptVersion, '/'));
        }

        return $widgetScriptUrl;
    }

    /**
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public static function getWidgetVariables(?int $productId = null): array
    {
        $productData = self::getProductData($productId);

        return [
            'WIDGET_SCRIPT_URL' => self::getWidgetScriptUrl(),
            'PRODUCT_ID' => $productData['product_id'],
            'PRODUCT_PRICE' => $productData['price'],
            'PLATFORM' => 'prestashop',
            'PLATFORM_VERSION' => _PS_VERSION_,
            'PLATFORM_DOMAIN' => \Tools::getShopDomain(),
            'PLUGIN_VERSION' => COMFINO_VERSION,
            'AVAILABLE_OFFER_TYPES_URL' => $productData['avail_offers_url'],
            'PRODUCT_DETAILS_URL' => $productData['product_details_url'],
        ];
    }

    public static function getConfigurationValues(string $optionsGroup, array $optionsToReturn = []): array
    {
        if (!array_key_exists($optionsGroup, self::CONFIG_OPTIONS)) {
            return [];
        }

        return count($optionsToReturn)
            ? self::getInstance()->getConfigurationValues($optionsToReturn)
            : self::getInstance()->getConfigurationValues(self::CONFIG_OPTIONS[$optionsGroup]);
    }

    public static function getDefaultValue(string $optionName)
    {
        return self::getDefaultConfigurationValues()[$optionName] ?? null;
    }

    public static function getDefaultConfigurationValues(): array
    {
        return [
            'COMFINO_PAYMENT_TEXT' => '(Raty | Kup Teraz, Zapłać Później | Finansowanie dla Firm)',
            'COMFINO_MINIMAL_CART_AMOUNT' => 30,
            'COMFINO_IS_SANDBOX' => false,
            'COMFINO_DEBUG' => false,
            'COMFINO_SERVICE_MODE' => false,
            'COMFINO_PRODUCT_CATEGORY_FILTERS' => '',
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => 'INSTALLMENTS_ZERO_PERCENT,PAY_LATER',
            'COMFINO_WIDGET_ENABLED' => false,
            'COMFINO_WIDGET_KEY' => '',
            'COMFINO_WIDGET_PRICE_SELECTOR' => COMFINO_PS_17 ? 'span.current-price-value' : 'span[itemprop=price]',
            'COMFINO_WIDGET_TARGET_SELECTOR' => 'div.product-actions',
            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => '',
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 0,
            'COMFINO_WIDGET_TYPE' => 'extended-modal',
            'COMFINO_WIDGET_OFFER_TYPE' => 'CONVENIENT_INSTALLMENTS',
            'COMFINO_WIDGET_EMBED_METHOD' => 'INSERT_INTO_LAST',
            'COMFINO_WIDGET_CODE' => WidgetInitScriptHelper::getInitialWidgetCode(),
            'COMFINO_WIDGET_PROD_SCRIPT_VERSION' => '',
            'COMFINO_WIDGET_DEV_SCRIPT_VERSION' => '',
            'COMFINO_IGNORED_STATUSES' => implode(',', StatusManager::DEFAULT_IGNORED_STATUSES),
            'COMFINO_FORBIDDEN_STATUSES' => implode(',', StatusManager::DEFAULT_FORBIDDEN_STATUSES),
            'COMFINO_STATUS_MAP' => json_encode(ShopStatusManager::DEFAULT_STATUS_MAP),
            'COMFINO_JS_PROD_PATH' => '',
            'COMFINO_CSS_PROD_PATH' => 'css',
            'COMFINO_JS_DEV_PATH' => '',
            'COMFINO_CSS_DEV_PATH' => 'css',
            'COMFINO_API_CONNECT_TIMEOUT' => 1,
            'COMFINO_API_TIMEOUT' => 3,
            'COMFINO_API_CONNECT_NUM_ATTEMPTS' => 3,
        ];
    }

    public static function initConfigurationValues(): void
    {
        if (\Configuration::hasKey('COMFINO_API_KEY')) {
            // Avoid overwriting of existing configuration if plugin is reinstalled/upgraded.
            return;
        }

        foreach (self::getDefaultConfigurationValues() as $optName => $optValue) {
            \Configuration::updateValue($optName, $optValue);
        }
    }

    /**
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    private static function getProductData(?int $productId): array
    {
        $context = \Context::getContext();
        $availOffersUrl = ApiService::getControllerUrl('availableoffertypes', [], false);
        $productDetailsUrl = ApiService::getControllerUrl('productdetails', [], false);

        $price = 'null';

        if ($productId !== null) {
            $availOffersUrl .= ((strpos($availOffersUrl, '?') === false ? '?' : '&') . "product_id=$productId");
            $productDetailsUrl .= ((strpos($productDetailsUrl, '?') === false ? '?' : '&') . "product_id=$productId");

            if (($price = \Product::getPriceStatic($productId)) === null) {
                $price = 'null';
            } else {
                $price = (new Tools($context))->getFormattedPrice($price);
            }
        } else {
            $productId = 'null';
        }

        return [
            'product_id' => $productId,
            'price' => $price,
            'avail_offers_url' => $availOffersUrl,
            'product_details_url' => $productDetailsUrl,
        ];
    }

    private static function getAvailableConfigOptions(): array
    {
        if (self::$availConfigOptions === null) {
            self::$availConfigOptions = array_merge(array_merge(...array_values(self::CONFIG_OPTIONS)));
        }

        return self::$availConfigOptions;
    }
}
