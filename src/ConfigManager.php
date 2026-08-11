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

require_once _PS_MODULE_DIR_ . 'comfino/src/Product/CategoryFilter.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Product/CategoryTree/BuildStrategy.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Product/CategoryTree.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Tools.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/PaywallAuthTokenGenerator.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ShopEnvironmentReporter.php';
require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

use Comfino\Order\Cart;
use Comfino\Product\CategoryFilter;
use Comfino\Product\CategoryTree;
use Comfino\Product\CategoryTree\BuildStrategy;

class ConfigManager
{
    const COMFINO_SETTINGS_OPTIONS = [
        'payment_settings' => [
            'COMFINO_API_KEY',
            'COMFINO_PAYMENT_TEXT_ENABLED',
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
            'COMFINO_WIDGET_OFFER_TYPES',
            'COMFINO_WIDGET_EMBED_METHOD',
            'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS',
            'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
            'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
        ],
        'developer_settings' => [
            'COMFINO_IS_SANDBOX',
            'COMFINO_SANDBOX_API_KEY',
        ],
    ];

    /** Obsolete options removed together with the UMD widget/paywall integration. */
    const OBSOLETE_CONFIG_OPTIONS = [
        'COMFINO_WIDGET_CODE',
        'COMFINO_WIDGET_PROD_SCRIPT_VERSION',
        'COMFINO_WIDGET_DEV_SCRIPT_VERSION',
    ];

    /** Short-lived error logging token is renewed when it expires in less than one hour. */
    const ERROR_LOGGING_TOKEN_REFRESH_MARGIN = 3600;

    const ACCESSIBLE_CONFIG_OPTIONS = [
        'COMFINO_PAYMENT_TEXT_ENABLED',
        'COMFINO_PAYMENT_TEXT',
        'COMFINO_CHECKOUT_PRODUCT_TYPES',
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
        'COMFINO_WIDGET_OFFER_TYPES',
        'COMFINO_WIDGET_EMBED_METHOD',
        'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS',
        'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
        'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
        'COMFINO_JS_PROD_PATH',
        'COMFINO_CSS_PROD_PATH',
        'COMFINO_JS_DEV_PATH',
        'COMFINO_CSS_DEV_PATH',
    ];

    const CONFIG_OPTIONS_TYPES = [
        'COMFINO_MINIMAL_CART_AMOUNT' => 'float',
        'COMFINO_IS_SANDBOX' => 'bool',
        'COMFINO_PAYMENT_TEXT_ENABLED' => 'bool',
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
            'COMFINO_PAYMENT_TEXT_ENABLED' => false,
            'COMFINO_PAYMENT_TEXT' => 'Comfino',
            'COMFINO_CHECKOUT_PRODUCT_TYPES' => 'INSTALLMENTS_ZERO_PERCENT,PAY_LATER',
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
            'COMFINO_WIDGET_OFFER_TYPES' => 'CONVENIENT_INSTALLMENTS',
            'COMFINO_WIDGET_EMBED_METHOD' => 'INSERT_INTO_LAST',
            'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS' => false,
            'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL' => '',
            'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL' => '',
            'COMFINO_ERROR_LOGGING_ACCESS_TOKEN' => '',
            'COMFINO_ERROR_LOGGING_ACCESS_TOKEN_EXPIRES_AT' => 0,
            'COMFINO_REMOTE_FLAGS' => '',
            'COMFINO_REMOTE_FLAG_ATTRIBUTES' => '',
            'COMFINO_JS_PROD_PATH' => '',
            'COMFINO_CSS_PROD_PATH' => 'css',
            'COMFINO_JS_DEV_PATH' => '',
            'COMFINO_CSS_DEV_PATH' => 'css',
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
     * Removes configuration options left behind by the UMD widget/paywall integration.
     *
     * @return void
     */
    public function deleteObsoleteConfigurationValues()
    {
        foreach (self::OBSOLETE_CONFIG_OPTIONS as $option_name) {
            \Configuration::deleteByName($option_name);
        }
    }

    /**
     * Returns a valid error logging access token, claiming or renewing it when needed.
     *
     * The token authorizes browser-side error reports; failures are silent because neither the paywall
     * nor the widget may be blocked by it.
     *
     * @return string empty string when no token could be obtained
     */
    public function getErrorLoggingAccessToken()
    {
        $token = (string) $this->getConfigurationValue('COMFINO_ERROR_LOGGING_ACCESS_TOKEN');
        $expires_at = (int) $this->getConfigurationValue('COMFINO_ERROR_LOGGING_ACCESS_TOKEN_EXPIRES_AT');

        if ($token !== '' && $expires_at > (time() + self::ERROR_LOGGING_TOKEN_REFRESH_MARGIN)) {
            return $token;
        }

        $claimed_token = Api::claimErrorLoggingToken();

        if (!is_array($claimed_token)) {
            // Keep serving the current token (if any) until a renewal succeeds.
            return $token;
        }

        $expiry_timestamp = strtotime($claimed_token['expires_at']);

        \Configuration::updateValue('COMFINO_ERROR_LOGGING_ACCESS_TOKEN', $claimed_token['access_token']);
        \Configuration::updateValue(
            'COMFINO_ERROR_LOGGING_ACCESS_TOKEN_EXPIRES_AT',
            $expiry_timestamp !== false ? $expiry_timestamp : 0
        );

        $this->refreshRemoteFlagsIfChanged(Api::getLastResponseHeader('Comfino-Flags'));

        return $claimed_token['access_token'];
    }

    /**
     * @return string[]
     */
    public function getRemoteFlags()
    {
        $flags = explode(',', (string) $this->getConfigurationValue('COMFINO_REMOTE_FLAGS'));

        return array_values(array_filter(array_map('trim', $flags), static function ($flag) {
            return $flag !== '';
        }));
    }

    /**
     * @return array
     */
    public function getRemoteFlagAttributes()
    {
        $flag_attributes = json_decode((string) $this->getConfigurationValue('COMFINO_REMOTE_FLAG_ATTRIBUTES'), true);

        return is_array($flag_attributes) ? $flag_attributes : [];
    }

    /**
     * Updates the stored remote flags from the "Comfino-Flags" header of the error logging token response.
     *
     * Flag attributes are only ever set/changed together with their flag, so the dedicated attributes
     * endpoint is only re-fetched when the flag list itself changed - this saves an extra API call on
     * every other token refresh.
     *
     * @param string $flags_header_value
     *
     * @return void
     */
    private function refreshRemoteFlagsIfChanged($flags_header_value)
    {
        $remote_flags = array_values(array_unique(array_filter(
            array_map('trim', explode(',', (string) $flags_header_value)),
            static function ($flag) { return $flag !== ''; }
        )));
        sort($remote_flags);

        $stored_flags = $this->getRemoteFlags();
        sort($stored_flags);

        if ($remote_flags === $stored_flags) {
            return;
        }

        \Configuration::updateValue('COMFINO_REMOTE_FLAGS', implode(',', $remote_flags));

        $flag_attributes = Api::getUserSettingsFlags();

        \Configuration::updateValue(
            'COMFINO_REMOTE_FLAG_ATTRIBUTES',
            is_array($flag_attributes) ? json_encode($flag_attributes) : ''
        );
    }

    /**
     * Builds the product page widget configuration consumed by the CDN widget bridge script.
     *
     * Only keys the SDK knows are emitted, and null values are dropped so omitted options fall back to the
     * SDK defaults instead of overriding them with empty values.
     *
     * @param int|null $product_id
     *
     * @return array
     *
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function getWidgetConfig($product_id = null)
    {
        $settings = $this->getConfigurationValues('widget_settings');
        $product_data = $this->getProductData($product_id);
        $widget_key = (string) $settings['COMFINO_WIDGET_KEY'];

        $offer_types = array_values(array_filter(
            array_map('trim', explode(',', (string) $settings['COMFINO_WIDGET_OFFER_TYPES'])),
            static function ($offer_type) { return $offer_type !== ''; }
        ));

        $config = [
            'sdkScriptUrl' => Api::getSdkScriptUrl(),
            'environment' => Api::isSandboxMode() ? 'sandbox' : 'production',
            'widgetKey' => $widget_key !== '' ? $widget_key : null,
            'loggingToken' => PaywallAuthTokenGenerator::generateLoggingToken(
                $widget_key,
                $this->getErrorLoggingAccessToken()
            ),
            'widgetTargetSelector' => $settings['COMFINO_WIDGET_TARGET_SELECTOR'],
            'priceSelector' => $settings['COMFINO_WIDGET_PRICE_SELECTOR'],
            'priceObserverSelector' => $settings['COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR'] ?: null,
            'priceObserverLevel' => (int) $settings['COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'],
            'embedMethod' => $settings['COMFINO_WIDGET_EMBED_METHOD'],
            'widgetType' => $settings['COMFINO_WIDGET_TYPE'],
            'offerTypes' => count($offer_types) ? $offer_types : null,
            'showProviderLogos' => (bool) $settings['COMFINO_WIDGET_SHOW_PROVIDER_LOGOS'],
            'hasPriceInput' => false,
            'bannerCssUrl' => $settings['COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL'] ?: null,
            'calculatorCssUrl' => $settings['COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL'] ?: null,
            'price' => $product_data['price'],
            'productId' => $product_data['product_id'],
            'availableProductTypes' => $product_data['available_product_types'],
            'productCartDetails' => $product_data['product_cart_details'],
            'language' => \Context::getContext()->language->iso_code,
            'currency' => \Context::getContext()->currency->iso_code,
            'shopEnvironment' => ShopEnvironmentReporter::getFrontendEnvironment(['type' => 'product']),
        ];

        return array_filter(
            $config,
            static function ($value) { return $value !== null; }
        );
    }

    /**
     * Renders the JSON configuration block read by the widget bridge script loaded from the SDK CDN.
     *
     * The JSON is encoded defensively so no admin-controlled value (selectors, product names) can terminate
     * the script tag or smuggle entity references into the page.
     *
     * @param int|null $product_id
     *
     * @return string
     */
    public function renderWidgetConfigElement($product_id = null)
    {
        try {
            $config = $this->getWidgetConfig($product_id);
        } catch (\Exception $e) {
            return '';
        }

        $json = json_encode(
            $config,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            return '';
        }

        return '<script type="application/json" id="comfino-widget-config">' . $json . '</script>';
    }

    /**
     * @return array
     */
    public function getWidgetTypes()
    {
        $widget_types = Api::getWidgetTypes();

        if ($widget_types !== false) {
            $widget_types_list = [];

            foreach ($widget_types as $widget_type_code => $widget_type_name) {
                $widget_types_list[] = ['key' => $widget_type_code, 'name' => $widget_type_name];
            }
        } else {
            $widget_types_list = [
                ['key' => 'standard', 'name' => 'Widget standardowy'],
            ];
        }

        return $widget_types_list;
    }

    /**
     * @param string $list_type
     * @param bool $use_public_names When true, returns customer-facing names for the frontend SDK instead
     *                               of the admin-facing names used in the plugin's own settings UI.
     *
     * @return array
     */
    public function getOfferTypes($list_type = 'sale_settings', $use_public_names = false)
    {
        if ($list_type === 'sale_settings') {
            $list_type = 'paywall';
        } else {
            $list_type = 'widget';
        }

        $product_types = Api::getProductTypes($list_type, $use_public_names);

        if ($product_types !== false) {
            $offer_types = [];

            foreach ($product_types as $product_type_code => $product_type_name) {
                $offer_types[] = ['key' => $product_type_code, 'name' => $product_type_name];
            }
        } else {
            $offer_types = [
                [
                    'key' => Api::INSTALLMENTS_ZERO_PERCENT,
                    'name' => $this->module->l('Zero percent installments'),
                ],
                [
                    'key' => Api::CONVENIENT_INSTALLMENTS,
                    'name' => $this->module->l('Convenient installments'),
                ],
                ['key' => Api::PAY_LATER, 'name' => $this->module->l('Pay later')],
            ];
        }

        return $offer_types;
    }

    /**
     * @return string[]
     */
    public function getCheckoutProductTypes()
    {
        $product_types = explode(',', (string) $this->getConfigurationValue('COMFINO_CHECKOUT_PRODUCT_TYPES'));

        return array_values(array_filter(array_map('trim', $product_types), static function ($product_type) {
            return $product_type !== '';
        }));
    }

    /**
     * Returns the checkout payment method item label: either the merchant's custom text
     * (COMFINO_PAYMENT_TEXT) when COMFINO_PAYMENT_TEXT_ENABLED is on, or the display names of the selected
     * financial product types (COMFINO_CHECKOUT_PRODUCT_TYPES, at most 2) joined into a single label.
     *
     * @return string|null
     */
    public function getPaymentMethodLabel()
    {
        if ($this->getConfigurationValue('COMFINO_PAYMENT_TEXT_ENABLED')) {
            $text = (string) $this->getConfigurationValue('COMFINO_PAYMENT_TEXT');

            return $text !== '' ? $text : null;
        }

        $selected_product_types = $this->getCheckoutProductTypes();

        if (empty($selected_product_types) || !is_array($product_type_names = Api::getProductTypes('paywall', true))) {
            return null;
        }

        $labels = [];

        foreach ($selected_product_types as $product_type_code) {
            if (isset($product_type_names[$product_type_code])) {
                $labels[] = $product_type_names[$product_type_code];
            }
        }

        return $labels ? implode(' | ', $labels) : null;
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
     * @return string[] [['prodTypeCode' => 'prodTypeName'], ...]
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
     * @return string[]
     */
    public function getAllProductCategories()
    {
        static $categories = null;

        $language = \Context::getContext()->language->iso_code;

        if ($categories === null || !isset($categories[$language])) {
            if ($categories === null) {
                $categories = [];
            } else {
                $categories[$language] = [];
            }

            foreach (\Category::getSimpleCategories(\Context::getContext()->language->id) as $category) {
                $categories[$language][$category['id_category']] = $category['name'];
            }
        }

        return $categories[$language];
    }

    /**
     * @return CategoryTree
     */
    public function getCategoriesTree()
    {
        /** @var CategoryTree $categories_tree */
        static $categories_tree = null;

        if ($categories_tree === null) {
            $categories_tree = new CategoryTree(new BuildStrategy());
        }

        return $categories_tree;
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
     * @param string $list_type
     * @param Cart $cart
     * @param bool $return_only_array
     *
     * @return string[]|null
     */
    public function getAllowedProductTypes($list_type, Cart $cart, $return_only_array = false)
    {
        $available_product_types = array_keys(Api::getProductTypes($list_type));
        $category_filter = new CategoryFilter($this->getCategoriesTree());

        $allowed_product_types = [];
        $excluded_category_ids_by_product_type = $this->getProductCategoryFilters();

        foreach ($available_product_types as $product_type) {
            if (array_key_exists($product_type, $excluded_category_ids_by_product_type)) {
                if ($category_filter->isCartValid($cart, $excluded_category_ids_by_product_type[$product_type])) {
                    $allowed_product_types[] = $product_type;
                }
            } else {
                $allowed_product_types[] = $product_type;
            }
        }

        if ($return_only_array) {
            return $allowed_product_types;
        }

        return count($available_product_types) !== count($allowed_product_types) ? $allowed_product_types : null;
    }

    /**
     * @return array
     *
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    private function getProductData($product_id)
    {
        $price = null;
        $product_cart_details = null;

        if ($product_id !== null) {
            $product = new \Product($product_id);

            if (!\Validate::isLoadedObject($product)) {
                $available_product_types = array_keys(Api::getProductTypes('widget'));
            } else {
                $shop_cart = OrderManager::getShopCartFromProduct($product);

                // The SDK expects the price in the smallest currency unit (grosze), not as a formatted amount.
                $price = (int) round(
                    (new Tools(\Context::getContext()))->getFormattedPrice($product->getPrice()) * 100
                );
                $available_product_types = $this->getAllowedProductTypes('widget', $shop_cart, true);
                $product_cart_details = $shop_cart->getAsArray();
            }
        } else {
            $available_product_types = array_keys(Api::getProductTypes('widget'));
        }

        return [
            'product_id' => $product_id !== null ? (int) $product_id : null,
            'price' => $price,
            'available_product_types' => $available_product_types,
            'product_cart_details' => $product_cart_details,
        ];
    }
}
