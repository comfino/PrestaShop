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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Tools.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/PresentationType.php';
require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

class ConfigManager
{
    const COMFINO_SETTINGS_OPTIONS = [
        'payment_settings' => [
            'COMFINO_API_KEY',
            'COMFINO_PAYMENT_TEXT',
            'COMFINO_MINIMAL_CART_AMOUNT',
        ],
        'sale_settings' => [
            'COMFINO_PRODUCT_CATEGORY_FILTERS',
        ],
        'widget_settings' => [
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
        ],
        'developer_settings' => [
            'COMFINO_IS_SANDBOX',
            'COMFINO_SANDBOX_API_KEY',
        ],
    ];

    const ACCESSIBLE_CONFIG_OPTIONS = [
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
    ];

    const CONFIG_OPTIONS_TYPES = [
        'COMFINO_MINIMAL_CART_AMOUNT' => 'float',
        'COMFINO_IS_SANDBOX' => 'bool',
        'COMFINO_WIDGET_ENABLED' => 'bool',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 'int',
    ];

    /**
     * @var \PaymentModule
     */
    private $module;

    /**
     * @param \PaymentModule $module
     */
    public function __construct($module)
    {
        $this->module = $module;
    }

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
     * @param string $opt_name
     * @param mixed $opt_value
     *
     * @return void
     */
    public function setConfigurationValue($opt_name, $opt_value)
    {
        \Configuration::updateValue($opt_name, $opt_value);
    }

    /**
     * @param string $options_group
     *
     * @return string[]
     */
    public function getConfigurationValues($options_group, array $options_to_return = [])
    {
        $config_values = [];

        if (!array_key_exists($options_group, self::COMFINO_SETTINGS_OPTIONS)) {
            return [];
        }

        if (count($options_to_return)) {
            foreach ($options_to_return as $opt_name) {
                if (in_array($opt_name, self::COMFINO_SETTINGS_OPTIONS[$options_group], true)) {
                    $config_values[$opt_name] = \Configuration::get($opt_name);
                }
            }
        } else {
            foreach (self::COMFINO_SETTINGS_OPTIONS[$options_group] as $opt_name) {
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

        foreach (\OrdersList::CUSTOM_ORDER_STATUSES as $status_code => $status_details) {
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

        foreach (\OrdersList::CUSTOM_ORDER_STATUSES as $status_code => $status_details) {
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
            '{WIDGET_SCRIPT_URL}' => Api::getWidgetScriptUrl(),
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
    public function getWidgetTypes()
    {
        $widget_types = \Comfino\Api::getWidgetTypes();

        if ($widget_types !== false) {
            $widget_types_list = [];

            foreach ($widget_types as $widget_type_code => $widget_type_name) {
                $widget_types_list[] = ['key' => $widget_type_code, 'name' => $widget_type_name];
            }
        } else {
            $widget_types_list = [
                ['key' => 'simple', 'name' => $this->module->l('Textual widget')],
                ['key' => 'mixed', 'name' => $this->module->l('Graphical widget with banner')],
                [
                    'key' => 'with-modal',
                    'name' => $this->module->l('Graphical widget with installments calculator'),
                ],
                [
                    'key' => 'extended-modal',
                    'name' => $this->module->l('Graphical widget with extended installments calculator'),
                ],
            ];
        }

        return $widget_types_list;
    }

    /**
     * @return array
     */
    public function getOfferTypes()
    {
        $product_types = \Comfino\Api::getProductTypes();

        if ($product_types !== false) {
            $offer_types = [];

            foreach ($product_types as $product_type_code => $product_type_name) {
                $offer_types[] = ['key' => $product_type_code, 'name' => $product_type_name];
            }
        } else {
            $offer_types = [
                [
                    'key' => \Comfino\Api::INSTALLMENTS_ZERO_PERCENT,
                    'name' => $this->module->l('Zero percent installments'),
                ],
                [
                    'key' => \Comfino\Api::CONVENIENT_INSTALLMENTS,
                    'name' => $this->module->l('Convenient installments'),
                ],
                ['key' => \Comfino\Api::PAY_LATER, 'name' => $this->module->l('Pay later')],
            ];
        }

        return $offer_types;
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

        return array_intersect_key($prod_types_assoc, $cat_filter_avail_prod_types);
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

        if (!in_array($product_type, array_map(
            static function ($prod_type) { return strtoupper(trim($prod_type)); },
            explode(',', $this->getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES'))
        ), true)) {
            return true;
        }

        if ($product_category_filters === null) {
            $product_category_filters = $this->getProductCategoryFilters();
        }

        if (isset($product_category_filters[$product_type]) && count($product_category_filters[$product_type])) {
            $excluded_cat_ids = $product_category_filters[$product_type];

            foreach ($products as $product) {
                $category_id = (int) $product['id_category_default'];

                if (in_array($category_id, $excluded_cat_ids, true)
                    || count(array_intersect($excluded_cat_ids, array_map(
                        static function (\Category $category) { return $category->id; },
                        (new \Category($category_id))->getAllChildren()->getResults()
                    )))
                ) {
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
            $avail_offers_url .= "&product_id=$product_id";

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
