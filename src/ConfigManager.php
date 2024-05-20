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

use Comfino\Common\Backend\CacheManager;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Configuration\StorageAdapter;
use Comfino\Extended\Api\Client;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Psr\Http\Client\ClientExceptionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Tools.php';
require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

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
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY
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
    ];

    public const CONFIG_OPTIONS_TYPES = [
        'COMFINO_MINIMAL_CART_AMOUNT' => 'float',
        'COMFINO_IS_SANDBOX' => 'bool',
        'COMFINO_WIDGET_ENABLED' => 'bool',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 'int',
    ];

    /** @var \PaymentModule */
    private static $module;

    /** @var ConfigurationManager */
    private static $configuration_manager;

    public static function getInstance(/*\PaymentModule $module*/): ConfigurationManager
    {
        //self::$module = $module;

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

        foreach (array_merge(array_merge(...array_values(self::CONFIG_OPTIONS))) as $opt_name) {
            $configuration[$opt_name] = \Configuration::get($opt_name);
        }

        return $configuration;
    }

    public static function save(array $configuration): void
    {
        foreach ($configuration as $opt_name => $opt_value) {
            \Configuration::updateValue($opt_name, $opt_value);
        }
    }

    public static function getEnvironmentInfo(): array
    {
        return [
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
    }

    /**
     * @param string $list_type
     * @return array
     */
    public function getOfferTypes($list_type = 'sale_settings'): array
    {
        if ($list_type === 'sale_settings') {
            $list_type = 'paywall';
        } else {
            $list_type = 'widget';
        }

        \Comfino\ApiClient::init($this->module);

        $product_types = \Comfino\ApiClient::getProductTypes($list_type);

        if ($product_types !== false) {
            $offer_types = [];

            foreach ($product_types as $product_type_code => $product_type_name) {
                $offer_types[] = ['key' => $product_type_code, 'name' => $product_type_name];
            }
        } else {
            $offer_types = [
                [
                    'key' => \Comfino\ApiClient::INSTALLMENTS_ZERO_PERCENT,
                    'name' => $this->module->l('Zero percent installments'),
                ],
                [
                    'key' => \Comfino\ApiClient::CONVENIENT_INSTALLMENTS,
                    'name' => $this->module->l('Convenient installments'),
                ],
                ['key' => \Comfino\ApiClient::PAY_LATER, 'name' => $this->module->l('Pay later')],
            ];
        }

        return $offer_types;
    }

    public static function getWidgetKey(): string
    {
        return self::getInstance()->getConfigurationValue('COMFINO_WIDGET_KEY');
    }
    /* -------------------------------------------------- */

    /**
     * @param string $opt_name
     *
     * @return string
     */
    public function getConfigurationValue($opt_name)
    {
        return \Configuration::get($opt_name);
    }

    /**
     * @param string $options_group
     *
     * @return string[]
     */
    public function getConfigurationValues($options_group, array $options_to_return = [])
    {
        $config_values = [];

        if (!array_key_exists($options_group, self::CONFIG_OPTIONS)) {
            return [];
        }

        if (count($options_to_return)) {
            foreach ($options_to_return as $opt_name) {
                if (in_array($opt_name, self::CONFIG_OPTIONS[$options_group], true)) {
                    $config_values[$opt_name] = \Configuration::get($opt_name);
                }
            }
        } else {
            foreach (self::CONFIG_OPTIONS[$options_group] as $opt_name) {
                $config_values[$opt_name] = \Configuration::get($opt_name);
            }
        }

        return $config_values;
    }

    /**
     * @return void
     */
    public function initConfigurationValues()
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
            'COMFINO_WIDGET_CODE' => $this->getInitialWidgetCode(),
            'COMFINO_WIDGET_PROD_SCRIPT_VERSION' => '',
            'COMFINO_WIDGET_DEV_SCRIPT_VERSION' => '',
        ];

        foreach ($initial_config_values as $opt_name => $opt_value) {
            \Configuration::updateValue($opt_name, $opt_value);
        }
    }

    /**
     * @return array
     */
    public function returnConfigurationOptions()
    {
        $configuration_options = [];

        foreach (self::ACCESSIBLE_CONFIG_OPTIONS as $opt_name) {
            $configuration_options[$opt_name] = \Configuration::get($opt_name);

            if (array_key_exists($opt_name, self::CONFIG_OPTIONS_TYPES)) {
                switch (self::CONFIG_OPTIONS_TYPES[$opt_name]) {
                    case 'bool':
                        $configuration_options[$opt_name] = (bool) $configuration_options[$opt_name];
                        break;

                    case 'int':
                        $configuration_options[$opt_name] = (int) $configuration_options[$opt_name];
                        break;

                    case 'float':
                        $configuration_options[$opt_name] = (float) $configuration_options[$opt_name];
                        break;
                }
            }
        }

        return $configuration_options;
    }

    /**
     * @param array $configuration_options
     * @param bool $only_accessible_options
     *
     * @return void
     */
    public function updateConfiguration($configuration_options, $only_accessible_options = true)
    {
        foreach ($configuration_options as $opt_name => $opt_value) {
            if ($only_accessible_options && !in_array($opt_name, self::ACCESSIBLE_CONFIG_OPTIONS, true)) {
                continue;
            }

            \Configuration::updateValue($opt_name, $opt_value);
        }
    }

    /**
     * @return bool
     */
    public function addCustomOrderStatuses()
    {
        $languages = \Language::getLanguages(false);

        foreach (OrderManager::CUSTOM_ORDER_STATUSES as $status_code => $status_details) {
            $comfino_status_id = \Configuration::get($status_code);

            if (!empty($comfino_status_id) && \Validate::isInt($comfino_status_id)) {
                $order_status = new \OrderState($comfino_status_id);

                if (\Validate::isLoadedObject($order_status)) {
                    // Update existing status definition.
                    $order_status->color = $status_details['color'];
                    $order_status->paid = $status_details['paid'];
                    $order_status->deleted = $status_details['deleted'];

                    $order_status->update();

                    continue;
                }
            } elseif ($status_details['deleted']) {
                // Ignore deleted statuses in first time plugin installations.
                continue;
            }

            // Add a new status definition.
            $order_status = new \OrderState();
            $order_status->send_email = false;
            $order_status->invoice = false;
            $order_status->color = $status_details['color'];
            $order_status->unremovable = false;
            $order_status->logable = false;
            $order_status->module_name = 'comfino';
            $order_status->paid = $status_details['paid'];

            foreach ($languages as $language) {
                $status_name = $language['iso_code'] === 'pl' ? $status_details['name_pl'] : $status_details['name'];
                $order_status->name[$language['id_lang']] = $status_name;
            }

            if ($order_status->add()) {
                \Configuration::updateValue($status_code, $order_status->id);
            }
        }

        return true;
    }

    /**
     * @return void
     */
    public function updateOrderStatuses()
    {
        $languages = \Language::getLanguages(false);

        foreach (OrderManager::CUSTOM_ORDER_STATUSES as $status_code => $status_details) {
            $comfino_status_id = \Configuration::get($status_code);

            if (!empty($comfino_status_id) && \Validate::isInt($comfino_status_id)) {
                $order_status = new \OrderState($comfino_status_id);

                if (\Validate::isLoadedObject($order_status)) {
                    // Update existing status definition.
                    foreach ($languages as $language) {
                        $status_name = $language['iso_code'] === 'pl' ? $status_details['name_pl'] : $status_details['name'];
                        $order_status->name[$language['id_lang']] = $status_name;
                    }

                    $order_status->color = $status_details['color'];
                    $order_status->paid = $status_details['paid'];
                    $order_status->deleted = $status_details['deleted'];

                    $order_status->save();
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getWidgetVariables($product_id = null)
    {
        $product_data = $this->getProductData($product_id);

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

    /**
     * @param string $last_widget_code_hash
     *
     * @return void
     */
    public function updateWidgetCode($last_widget_code_hash)
    {
        $initial_widget_code = $this->getInitialWidgetCode();
        $current_widget_code = $this->getCurrentWidgetCode();

        if (md5($current_widget_code) === $last_widget_code_hash) {
            // Widget code not changed since last installed version - safely replace with new one.
            \Configuration::updateValue('COMFINO_WIDGET_CODE', $initial_widget_code);
        }
    }

    public function getCurrentWidgetCode($product_id = null)
    {
        $widget_code = trim(str_replace("\r", '', \Configuration::get('COMFINO_WIDGET_CODE')));
        $product_data = $this->getProductData($product_id);

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
     * @return string
     */
    public function getInitialWidgetCode()
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

    /**
     * @return array
     */
    public function getProductCategoryFilters()
    {
        $categories = [];
        $categories_str = $this->getConfigurationValue('COMFINO_PRODUCT_CATEGORY_FILTERS');

        if (!empty($categories_str)) {
            $categories = json_decode($categories_str, true);
        }

        return $categories;
    }

    /**
     * @return array
     */
    public function getCatFilterAvailProdTypes(array $prod_types)
    {
        $prod_types_assoc = [];
        $cat_filter_avail_prod_types = [];

        foreach ($prod_types as $prod_type) {
            $prod_types_assoc[$prod_type['key']] = $prod_type['name'];
        }

        foreach (explode(',', $this->getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES')) as $prod_type) {
            $cat_filter_avail_prod_types[strtoupper(trim($prod_type))] = null;
        }

        if (empty($avail_prod_types = array_intersect_key($prod_types_assoc, $cat_filter_avail_prod_types))) {
            $avail_prod_types = $prod_types_assoc;
        }

        return $avail_prod_types;
    }

    /**
     * @return array
     */
    public function getAllProductCategories()
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

    /**
     * @param string $product_type Financial product type (offer type)
     * @param array $products Products in the cart
     *
     * @return bool
     */
    public function isFinancialProductAvailable($product_type, array $products)
    {
        static $product_category_filters = null;
        static $cat_filter_avail_prod_types = null;

        if ($cat_filter_avail_prod_types === null) {
            $cat_filter_avail_prod_types = array_keys($this->getCatFilterAvailProdTypes($this->getOfferTypes()));
        }

        if (!in_array($product_type, $cat_filter_avail_prod_types, true)) {
            return true;
        }

        if ($product_category_filters === null) {
            $product_category_filters = $this->getProductCategoryFilters();
        }

        if (isset($product_category_filters[$product_type]) && count($product_category_filters[$product_type])) {
            $excluded_cat_ids = $product_category_filters[$product_type];
            $available_cat_ids = array_diff(array_keys($this->getAllProductCategories()), $excluded_cat_ids);

            $parent_categories = [];

            foreach ($products as $product) {
                $category_id = (int) $product['id_category_default'];

                if (in_array($category_id, $excluded_cat_ids, true)) {
                    foreach (array_diff($available_cat_ids, [$category_id]) as $cat_id) {
                        if (!isset($parent_categories[$cat_id])) {
                            $parent_categories[$cat_id] = [];

                            if (is_array($cat_parents = (new \Category($cat_id))->getParentsCategories())) {
                                foreach ($cat_parents as $category) {
                                    if ($category['id_category'] !== $cat_id) {
                                        $parent_categories[$cat_id][] = $category['id_category'];
                                    }
                                }
                            }
                        }

                        if (in_array($category_id, $parent_categories[$cat_id], true)) {
                            continue 2;
                        }
                    }

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return array
     */
    private function getProductData($product_id)
    {
        $context = \Context::getContext();
        $avail_offers_url = $context->link->getModuleLink($this->module->name, 'availableoffertypes', [], true);

        $price = 'null';

        if ($product_id !== null) {
            $avail_offers_url .= ((strpos($avail_offers_url, '?') === false ? '?' : '&') . "product_id=$product_id");

            if (($price = \Product::getPriceStatic($product_id)) === null) {
                $price = 'null';
            } else {
                $price = (new \Comfino\Tools($context))->getFormattedPrice($price);
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
}
