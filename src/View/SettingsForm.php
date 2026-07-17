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

namespace Comfino\View;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\PluginShared\CacheManager;
use Comfino\Telemetry\ShopEnvironmentReporter;
use Comfino\Update\UpdateManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SettingsForm
{
    public const ERROR_LOG_NUM_LINES = 100;
    public const DEBUG_LOG_NUM_LINES = 200;
    public const COMFINO_SUPPORT_EMAIL = 'pomoc@comfino.pl';
    public const COMFINO_SUPPORT_PHONE = '887-106-027';

    /**
     * Processes form submission from module configuration page.
     *
     * Handles various form submissions including:
     * - Module reset.
     * - Error log clearing.
     * - Debug log clearing.
     * - Configuration updates for all settings tabs.
     *
     * @return array Configuration and output data for template rendering
     */
    public static function processForm(): array
    {
        ErrorLogger::init();

        $activeTab = 'payment_settings';
        $outputType = 'success';
        $output = [];
        $widgetKeyError = false;
        $widgetKey = ConfigManager::getConfigurationValue('COMFINO_WIDGET_KEY', '');

        $errorEmptyMsg = Main::translate('Field "%s" can not be empty.');
        $errorNumericFormatMsg = Main::translate('Field "%s" has wrong numeric format.');

        $configurationOptions = [];

        // Handle module reset submission.
        if (\Tools::isSubmit('submit_module_reset')) {
            $activeTab = 'plugin_diagnostics';

            try {
                $resetStats = Main::reset(Main::getModule());
                $hasErrors = $resetStats['config_failed'] > 0
                    || $resetStats['hooks_failed'] > 0
                    || $resetStats['statuses_create_failed'] > 0
                    || $resetStats['statuses_update_failed'] > 0;

                if ($hasErrors) {
                    $outputType = 'warning';
                    $output[] = Main::translate('Module reset completed with some errors.');
                } else {
                    $output[] = Main::translate('Module reset completed successfully.');
                }

                $output[] = sprintf(
                    Main::translate('Configuration: %d repaired, %d failed'),
                    $resetStats['config_repaired'],
                    $resetStats['config_failed']
                );
                $output[] = sprintf(
                    Main::translate('Hooks: %d registered, %d failed'),
                    $resetStats['hooks_registered'],
                    $resetStats['hooks_failed']
                );
                $output[] = sprintf(
                    Main::translate('Order statuses: %d created, %d updated, %d failed'),
                    $resetStats['statuses_created'],
                    $resetStats['statuses_updated'],
                    $resetStats['statuses_create_failed'] + $resetStats['statuses_update_failed']
                );
            } catch (\Exception $e) {
                $outputType = 'error';
                $output[] = Main::translate('Module reset failed') . ': ' . $e->getMessage();
            }
        } elseif (\Tools::isSubmit('submit_clear_error_log')) {
            $activeTab = 'plugin_diagnostics';

            try {
                ErrorLogger::clearLogs();

                $output[] = Main::translate('Error log cleared successfully.');
            } catch (\Exception $e) {
                $outputType = 'error';
                $output[] = Main::translate('Error log clearing failed') . ': ' . $e->getMessage();
            }
        } elseif (\Tools::isSubmit('submit_clear_debug_log')) {
            $activeTab = 'plugin_diagnostics';

            try {
                DebugLogger::clearLogs();

                $output[] = Main::translate('Debug log cleared successfully.');
            } catch (\Exception $e) {
                $outputType = 'error';
                $output[] = Main::translate('Debug log clearing failed') . ': ' . $e->getMessage();
            }
        } elseif (\Tools::isSubmit('submit_configuration')) {
            $activeTab = \Tools::getValue('active_tab');

            foreach (ConfigManager::CONFIG_OPTIONS[$activeTab] as $optionName => $optionType) {
                if ($optionName !== 'COMFINO_WIDGET_KEY') {
                    if ($optionName === 'COMFINO_WIDGET_OFFER_TYPES') {
                        $configurationOptions[$optionName] = [];
                        $offerTypes = SettingsManager::getProductTypesSelectList(
                            ProductTypesListTypeEnum::LIST_TYPE_WIDGET
                        );

                        foreach ($offerTypes as $offerType) {
                            if (\Tools::getIsset("{$optionName}_{$offerType['key']}")) {
                                $configurationOptions[$optionName][] = $offerType['key'];
                            }
                        }
                    } else {
                        $configurationOptions[$optionName] = \Tools::getValue($optionName);
                    }
                }
            }

            switch ($activeTab) {
                case 'payment_settings':
                case 'developer_settings':
                    if ($activeTab === 'payment_settings') {
                        $sandboxMode = ConfigManager::isSandboxMode();
                        $apiKey = $sandboxMode
                            ? ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY')
                            : \Tools::getValue('COMFINO_API_KEY');

                        if (\Tools::isEmpty(\Tools::getValue('COMFINO_API_KEY'))) {
                            $output[] = sprintf($errorEmptyMsg, Main::translate('Production environment API key'));
                        }
                        if (\Tools::isEmpty(\Tools::getValue('COMFINO_PAYMENT_TEXT'))) {
                            $output[] = sprintf($errorEmptyMsg, Main::translate('Payment text'));
                        }
                        if (\Tools::isEmpty(\Tools::getValue('COMFINO_MINIMAL_CART_AMOUNT'))) {
                            $output[] = sprintf($errorEmptyMsg, Main::translate('Minimal amount in cart'));
                        } elseif (!is_numeric(\Tools::getValue('COMFINO_MINIMAL_CART_AMOUNT'))) {
                            $output[] = sprintf($errorNumericFormatMsg, Main::translate('Minimal amount in cart'));
                        }
                    } else {
                        $sandboxMode = (bool) \Tools::getValue('COMFINO_IS_SANDBOX');
                        $apiKey = $sandboxMode
                            ? \Tools::getValue('COMFINO_SANDBOX_API_KEY')
                            : ConfigManager::getConfigurationValue('COMFINO_API_KEY');

                        if (\Tools::getIsset('COMFINO_DEV_ENV_VARS')) {
                            ConfigManager::updateConfigurationValue(
                                'COMFINO_DEV_ENV_VARS',
                                \Tools::getValue('COMFINO_DEV_ENV_VARS')
                            );
                        }
                    }

                    $apiClient = ApiClient::getInstance($sandboxMode, $apiKey);

                    if (!empty($apiKey) && !count($output)) {
                        $cacheInvalidateUrl = ApiService::getEndpointUrl('cacheInvalidate');
                        $configurationUrl = ApiService::getEndpointUrl('configuration');

                        try {
                            // Check if passed API key is valid.
                            $apiClient->isShopAccountActive($cacheInvalidateUrl, $configurationUrl);

                            try {
                                // If API key is valid fetch widget key from API endpoint.
                                $widgetKey = $apiClient->getWidgetKey();
                            } catch (\Throwable $e) {
                                ApiClient::processApiError(
                                    ($activeTab === 'payment_settings' ? 'Payment' : 'Developer') .
                                    ' settings error on page "' . Main::getRequestUri() . '" (Comfino API)',
                                    $e,
                                    OperationContext::Configuration
                                );

                                $output[] = $e->getMessage();
                                $outputType = 'error';
                                $widgetKeyError = true;

                                if (!empty(getenv('COMFINO_DEV'))) {
                                    $output[] = sprintf('Comfino API host: %s', $apiClient->getApiHost());
                                }
                            }
                        } catch (AuthorizationError|AccessDenied $e) {
                            $outputType = 'warning';
                            $output[] = sprintf(Main::translate('API key %s is not valid.'), $apiKey);

                            if (!empty(getenv('COMFINO_DEV'))) {
                                $output[] = sprintf('Comfino API host: %s', $apiClient->getApiHost());
                            }
                        } catch (\Throwable $e) {
                            ApiClient::processApiError(
                                ($activeTab === 'payment_settings' ? 'Payment' : 'Developer') .
                                ' settings error on page "' . Main::getRequestUri() . '" (Comfino API)',
                                $e,
                                OperationContext::Configuration
                            );

                            $outputType = 'error';
                            $output[] = $e->getMessage();

                            if (!empty(getenv('COMFINO_DEV'))) {
                                $output[] = sprintf('Comfino API host: %s', $apiClient->getApiHost());
                            }
                        }
                    }

                    $configurationOptions['COMFINO_WIDGET_KEY'] = $widgetKey;
                    break;

                case 'sale_settings':
                    $categoriesTree = ConfigManager::getCategoriesTree();
                    $productCategories = array_keys(ConfigManager::getAllProductCategories());
                    $productCategoryFilters = [];

                    foreach (\Tools::getValue('product_categories') as $productType => $categoryIds) {
                        $nodeIds = [];

                        foreach (explode(',', $categoryIds) as $categoryId) {
                            if (($categoryNode = $categoriesTree->getNodeById((int) $categoryId)) !== null
                                && count($pathNodes = $categoryNode->getPathToRoot()) > 0
                            ) {
                                $nodeIds[] = $categoriesTree->getPathNodeIds($pathNodes);
                            }
                        }

                        if (count($nodeIds) > 0) {
                            $productCategoryFilters[$productType] = array_values(array_diff(
                                $productCategories,
                                ...$nodeIds
                            ));
                        } else {
                            $productCategoryFilters[$productType] = $productCategories;
                        }
                    }

                    $configurationOptions['COMFINO_PRODUCT_CATEGORY_FILTERS'] = $productCategoryFilters;

                    $productIdFilter = [];
                    $productIdFilterInput = \Tools::getValue('comfino_product_id_filter');

                    if (!empty($productIdFilterInput)) {
                        $productIdFilter = array_values(array_unique(array_filter(
                            array_map('intval', preg_split('/[\s,]+/', (string) $productIdFilterInput)),
                            static function (int $id): bool { return $id > 0; }
                        )));
                    }

                    $configurationOptions['COMFINO_PRODUCT_ID_FILTER'] = $productIdFilter;

                    if (!ConfigManager::getConfigurationValue('COMFINO_ALLOWED_PRODUCTS_CONFIG_ENABLED')) {
                        break;
                    }

                    $allowedProductsConfig = [];
                    $termLimitsData = \Tools::getValue('comfino_term_limits', []);
                    $validProductTypes = \Comfino\Api\Dto\Payment\LoanTypeEnum::values();

                    if (is_array($termLimitsData)) {
                        foreach ($termLimitsData as $productType => $limits) {
                            $productType = \Tools::safeOutput((string) $productType);

                            if (!in_array($productType, $validProductTypes, true)) {
                                $output[] = sprintf(
                                    Main::translate('Unknown product type "%s" in term limits — entry skipped.'),
                                    $productType
                                );

                                continue;
                            }

                            $maxTerm = isset($limits['maxTerm']) && $limits['maxTerm'] !== '' ? (int) $limits['maxTerm'] : null;
                            $minTerm = isset($limits['minTerm']) && $limits['minTerm'] !== '' ? (int) $limits['minTerm'] : null;
                            $termsRaw = isset($limits['terms']) && $limits['terms'] !== '' ? $limits['terms'] : null;
                            $terms = null;

                            if ($termsRaw !== null) {
                                $terms = array_values(array_filter(
                                    array_map('intval', explode(',', $termsRaw)),
                                    static function ($term) { return $term > 0; }
                                ));

                                if (empty($terms)) {
                                    $terms = null;
                                }
                            }

                            if ($minTerm !== null && $maxTerm !== null && $minTerm > $maxTerm) {
                                $output[] = sprintf(
                                    Main::translate('Term limits for "%s": minTerm must not exceed maxTerm.'),
                                    $productType
                                );

                                continue;
                            }

                            if ($maxTerm !== null || $minTerm !== null || $terms !== null) {
                                $allowedProductsConfig[] = array_filter(
                                    ['type' => $productType, 'maxTerm' => $maxTerm, 'minTerm' => $minTerm, 'terms' => $terms],
                                    static function ($value) { return $value !== null; }
                                );
                            }
                        }
                    }

                    $configurationOptions['COMFINO_ALLOWED_PRODUCTS_CONFIG'] = !empty($allowedProductsConfig)
                        ? $allowedProductsConfig
                        : null;

                    break;

                case 'widget_settings':
                    if (!is_numeric(\Tools::getValue('COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'))) {
                        $output[] = sprintf(
                            $errorNumericFormatMsg,
                            Main::translate('Price change detection - container hierarchy level')
                        );
                    }

                    $customCssUrlOptionNames = [
                        'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
                        'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
                    ];

                    foreach ($customCssUrlOptionNames as $customCssUrlOptionName) {
                        if (!empty($customCssUrl = \Tools::getValue($customCssUrlOptionName))) {
                            if (!\Validate::isUrl($customCssUrl)) {
                                $output[] = sprintf(
                                    Main::translate('Custom CSS URL "%s" is not valid.'),
                                    $customCssUrl
                                );
                            } elseif (!\Validate::isAbsoluteUrl($customCssUrl)) {
                                $output[] = sprintf(
                                    Main::translate('Custom CSS URL "%s" is not absolute.'),
                                    $customCssUrl
                                );
                            } elseif (stripos($customCssUrl, \Tools::getShopDomain()) === false) {
                                $output[] = sprintf(
                                    Main::translate('Custom CSS URL "%s" is not in shop domain "%s".'),
                                    $customCssUrl,
                                    \Tools::getShopDomain()
                                );
                            }
                        }
                    }

                    if (!count($output) && !empty($apiKey = ConfigManager::getApiKey())) {
                        // Update widget key.
                        $cacheInvalidateUrl = ApiService::getEndpointUrl('cacheInvalidate');
                        $configurationUrl = ApiService::getEndpointUrl('configuration');

                        try {
                            // Check if passed API key is valid.
                            ApiClient::getInstance()->isShopAccountActive($cacheInvalidateUrl, $configurationUrl);

                            try {
                                $widgetKey = ApiClient::getInstance()->getWidgetKey();
                            } catch (\Throwable $e) {
                                ApiClient::processApiError(
                                    'Widget settings error on page "' . Main::getRequestUri() . '" (Comfino API)',
                                    $e,
                                    OperationContext::Configuration
                                );

                                $output[] = $e->getMessage();
                                $outputType = 'error';
                                $widgetKeyError = true;
                            }
                        } catch (AuthorizationError|AccessDenied $e) {
                            $outputType = 'warning';
                            $output[] = sprintf(Main::translate('API key %s is not valid.'), $apiKey);
                        } catch (\Throwable $e) {
                            ApiClient::processApiError(
                                'Widget settings error on page "' . Main::getRequestUri() . '" (Comfino API)',
                                $e,
                                OperationContext::Configuration
                            );

                            $outputType = 'error';
                            $output[] = $e->getMessage();
                        }
                    }

                    $configurationOptions['COMFINO_WIDGET_KEY'] = $widgetKey;
                    break;
            }

            if (!$widgetKeyError && count($output)) {
                $outputType = 'warning';
                $output[] = Main::translate('Settings not updated.');
            } else {
                // Update plugin configuration.
                ConfigManager::updateConfiguration($configurationOptions, false);

                // Report the shop environment to Comfino after a successful settings save (fire-and-forget).
                if (!empty(ConfigManager::getApiKey())) {
                    ShopEnvironmentReporter::report();
                }

                $output[] = Main::translate('Settings updated.');
            }

            // Clear configuration and frontend cache.
            CacheManager::getCachePool()->clear();
        }

        return [
            'active_tab' => $activeTab,
            'output' => array_map('htmlspecialchars_decode', $output),
            'output_type' => $outputType,
            'logo_url' => ConfigManager::getLogoUrl(),
            'support_email_address' => self::COMFINO_SUPPORT_EMAIL,
            'support_email_subject' => sprintf(
                Main::translate('PrestaShop %s Comfino %s - question'),
                _PS_VERSION_,
                COMFINO_VERSION
            ),
            'support_email_body' => sprintf(
                'PrestaShop %s Comfino %s, PHP %s',
                _PS_VERSION_,
                COMFINO_VERSION,
                PHP_VERSION
            ),
            'contact_msg1' => Main::translate('Do you want to ask about something? Write to us at'),
            'contact_msg2' => sprintf(
                Main::translate(
                    'or contact us by phone. We are waiting on the number: %s. We will answer all your questions!'
                ),
                self::COMFINO_SUPPORT_PHONE
            ),
            'plugin_version' => COMFINO_VERSION,
            'multistore_scope_message' => self::getMultistoreScopeMessage(),
        ] + self::getUpdateInfoForTemplate();
    }

    /**
     * Builds an informational banner describing which shop-context scope the settings form is currently editing, so the
     * admin can tell "All Shops" (global/default) apart from a single shop or shop group. Returns an empty string when
     * PrestaShop Multistore is inactive (single-shop installs render no banner and behave exactly as before).
     */
    private static function getMultistoreScopeMessage(): string
    {
        if (!\Shop::isFeatureActive()) {
            return '';
        }

        $globalOptionsNote = Main::translate(
            'Developer, API timeout, caching, order-status and CSP options always stay global and are shared by every shop.'
        );

        switch (\Shop::getContext()) {
            case \Shop::CONTEXT_SHOP:
                return sprintf(
                    Main::translate('You are editing the Comfino configuration for shop "%s". %s'),
                    \Context::getContext()->shop->name,
                    $globalOptionsNote
                );

            case \Shop::CONTEXT_GROUP:
                $shopGroup = new \ShopGroup((int) \Shop::getContextShopGroupID(true));

                return sprintf(
                    Main::translate('You are editing the Comfino configuration for shop group "%s". %s'),
                    \Validate::isLoadedObject($shopGroup) ? $shopGroup->name : '',
                    $globalOptionsNote
                );

            default: // Shop::CONTEXT_ALL
                return Main::translate(
                    'PrestaShop Multistore is active. You are editing the default (All Shops) Comfino configuration. ' .
                    'Settings saved here apply to every shop that does not override them; select a shop in the top ' .
                    'bar to configure it individually.'
                );
        }
    }

    /**
     * The latest available release version and "what's new" HTML, shown in the config header next to the plugin version
     * - but only when a newer version is actually available (hidden when up to date).
     */
    private static function getUpdateInfoForTemplate(): array
    {
        $updateInfo = UpdateManager::checkForUpdates();

        if (empty($updateInfo['update_available']) || empty($updateInfo['github_version'])) {
            return ['update_available_message' => '', 'latest_release_description' => ''];
        }

        return [
            'update_available_message' => sprintf(
                Main::translate(
                    'New Comfino %s module version is available. You are using %s version. ' .
                    'Please update your Comfino module.'
                ),
                $updateInfo['github_version'],
                COMFINO_VERSION
            ),
            /* Server-sanitized already; re-purified here with PrestaShop's HTML purifier, so the template output
               stays safe per marketplace requirements. */
            'latest_release_description' => !empty($updateInfo['description_html'])
                ? \Tools::purifyHTML($updateInfo['description_html'])
                : '',
        ];
    }

    /**
     * Generates form fields configuration for module settings.
     *
     * Builds form field definitions for different configuration tabs:
     * - payment_settings: API key, minimal cart amount, paywall settings
     * - sale_settings: Product category filters
     * - widget_settings: Widget configuration and appearance
     * - developer_settings: Sandbox mode, debug mode, service mode
     * - plugin_diagnostics: Module reset, logs, diagnostics
     *
     * @param array $params Form parameters including config_tab and form_name
     *
     * @return array Form fields configuration for PrestaShop form rendering
     */
    public static function getFormFields(array $params): array
    {
        $fields = [];
        $configTab = $params['config_tab'] ?? '';
        $formName = $params['form_name'] ?? 'submit_configuration';

        switch ($configTab) {
            case 'payment_settings':
                $fields['payment_settings']['form'] = [];

                if (isset($params['messages'])) {
                    // Messages list in the form header (type => message): description, warning, success, error
                    $fields['payment_settings']['form'] = array_merge(
                        $fields['payment_settings']['form'],
                        $params['messages']
                    );
                }

                $fields['payment_settings']['form'] = array_merge(
                    $fields['payment_settings']['form'],
                    [
                        'input' => [
                            [
                                'type' => 'hidden',
                                'name' => 'active_tab',
                                'required' => false,
                            ],
                            [
                                'type' => 'text',
                                'label' => Main::translate('Production environment API key'),
                                'name' => 'COMFINO_API_KEY',
                                'required' => true,
                                'placeholder' => Main::translate('Please enter the key provided during registration'),
                            ],
                            [
                                'type' => 'text',
                                'label' => Main::translate('Payment text'),
                                'name' => 'COMFINO_PAYMENT_TEXT',
                                'required' => true,
                            ],
                            [
                                'type' => 'text',
                                'label' => Main::translate('Minimal amount in cart'),
                                'name' => 'COMFINO_MINIMAL_CART_AMOUNT',
                                'required' => true,
                            ],
                            [
                                'type' => 'switch',
                                'label' => Main::translate('Use order reference as external ID'),
                                'name' => 'COMFINO_USE_ORDER_REFERENCE',
                                'desc' => Main::translate('Use customer-visible order reference instead of numeric order ID for Comfino API integration. New orders only.'),
                                'is_bool' => true,
                                'values' => [
                                    [
                                        'id' => 'active_on',
                                        'value' => true,
                                        'label' => Main::translate('Yes'),
                                    ],
                                    [
                                        'id' => 'active_off',
                                        'value' => false,
                                        'label' => Main::translate('No'),
                                    ],
                                ],
                            ],
                            [
                                'type' => 'html',
                                'name' => 'paywall_settings_section',
                                'required' => false,
                                'html_content' => '<h3>' . Main::translate('Paywall settings') . '</h3>',
                            ],
                            [
                                'type' => 'switch',
                                'label' => Main::translate('Direct redirect mode'),
                                'name' => 'COMFINO_PAYWALL_DIRECT_REDIRECT',
                                'desc' => Main::translate('When enabled, the full paywall offer browser is not displayed. The order is submitted with the default financial product and the customer is redirected directly to the Comfino payment gateway.'),
                                'is_bool' => true,
                                'values' => [
                                    [
                                        'id' => 'paywall_direct_redirect_on',
                                        'value' => true,
                                        'label' => Main::translate('Yes'),
                                    ],
                                    [
                                        'id' => 'paywall_direct_redirect_off',
                                        'value' => false,
                                        'label' => Main::translate('No'),
                                    ],
                                ],
                            ],
                            [
                                'type' => 'text',
                                'label' => Main::translate('Custom paywall CSS style'),
                                'name' => 'COMFINO_PAYWALL_CUSTOM_CSS_URL',
                                'required' => false,
                                'desc' => Main::translate('URL for a custom CSS file injected into the paywall iframe. Only links from your store domain are allowed.'),
                            ],
                        ],
                        'submit' => [
                            'title' => Main::translate('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $formName,
                        ],
                    ]
                );
                break;

            case 'sale_settings':
                $productCategories = ConfigManager::getAllProductCategories();
                $productCategoryFilters = SettingsManager::getProductCategoryFilters();

                $productCategoryFilterInputs = [
                    [
                        'type' => 'hidden',
                        'name' => 'active_tab',
                        'required' => false,
                    ],
                ];

                foreach (SettingsManager::getCatFilterAvailProdTypes() as $prodTypeCode => $prodTypeName) {
                    $productCategoryFilterInputs[] = [
                        'type' => 'html',
                        'name' => $prodTypeCode . '_label',
                        'required' => false,
                        'html_content' => '<h3>' . $prodTypeName . '</h3>',
                    ];

                    if (isset($productCategoryFilters[$prodTypeCode])) {
                        $selectedCategories = array_diff(
                            array_keys($productCategories),
                            $productCategoryFilters[$prodTypeCode]
                        );
                    } else {
                        $selectedCategories = array_keys($productCategories);
                    }

                    $productCategoryFilterInputs[] = [
                        'type' => 'html',
                        'name' => 'product_category_filter[' . $prodTypeCode . ']',
                        'required' => false,
                        'html_content' => self::renderCategoryTree(
                            'product_categories',
                            $prodTypeCode,
                            $selectedCategories
                        ),
                    ];
                }

                $productCategoryFilterInputs[] = [
                    'type' => 'html',
                    'name' => 'comfino_product_id_filter',
                    'required' => false,
                    'html_content' => self::renderProductIdFilter(SettingsManager::getProductIdFilter()),
                ];

                if ((bool) ConfigManager::getConfigurationValue('COMFINO_ALLOWED_PRODUCTS_CONFIG_ENABLED')) {
                    $savedAllowedConfig = ConfigManager::getConfigurationValue('COMFINO_ALLOWED_PRODUCTS_CONFIG');
                    $savedConfigByType = [];

                    if (is_array($savedAllowedConfig)) {
                        foreach ($savedAllowedConfig as $entry) {
                            if (!empty($entry['type'])) {
                                $savedConfigByType[$entry['type']] = $entry;
                            }
                        }
                    }

                    $productCategoryFilterInputs[] = [
                        'type' => 'html',
                        'name' => 'allowed_products_config',
                        'required' => false,
                        'html_content' => self::renderAllowedProductsConfig(
                            SettingsManager::getAllowedProductsConfigAvailProdTypes(),
                            $savedConfigByType
                        ),
                    ];
                }

                $fields['sale_settings_category_filter']['form'] = [
                    'legend' => ['title' => Main::translate('Rules for the availability of financial products')],
                    'input' => $productCategoryFilterInputs,
                    'submit' => [
                        'title' => Main::translate('Save'),
                        'class' => 'btn btn-default pull-right',
                        'name' => $formName,
                    ],
                ];
                break;

            case 'widget_settings':
                $fields['widget_settings_basic']['form'] = ['legend' => ['title' => Main::translate('Basic settings')]];

                if (isset($params['messages'])) {
                    // Messages list in the form header (type => message): description, warning, success, error
                    $fields['widget_settings_basic']['form'] = array_merge(
                        $fields['widget_settings_basic']['form'],
                        $params['messages']
                    );
                }

                $fields['widget_settings_basic']['form'] = array_merge(
                    $fields['widget_settings_basic']['form'],
                    [
                        'input' => [
                            [
                                'type' => 'hidden',
                                'name' => 'active_tab',
                                'required' => false,
                            ],
                            [
                                'type' => 'switch',
                                'label' => Main::translate('Widget is active?'),
                                'name' => 'COMFINO_WIDGET_ENABLED',
                                'is_bool' => true,
                                'values' => [
                                    [
                                        'id' => 'widget_enabled',
                                        'value' => true,
                                        'label' => Main::translate('Enabled'),
                                    ],
                                    [
                                        'id' => 'widget_disabled',
                                        'value' => false,
                                        'label' => Main::translate('Disabled'),
                                    ],
                                ],
                            ],
                            [
                                'type' => 'hidden',
                                'label' => Main::translate('Widget key'),
                                'name' => 'COMFINO_WIDGET_KEY',
                                'required' => false,
                            ],
                            [
                                'type' => 'select',
                                'label' => Main::translate('Widget type'),
                                'name' => 'COMFINO_WIDGET_TYPE',
                                'required' => false,
                                'options' => [
                                    'query' => $params['widget_types'],
                                    'id' => 'key',
                                    'name' => 'name',
                                ],
                            ],
                            [
                                'type' => 'checkbox',
                                'label' => Main::translate('Offer types'),
                                'name' => 'COMFINO_WIDGET_OFFER_TYPES',
                                'required' => false,
                                'values' => [
                                    'query' => array_map(
                                        static function ($option) {
                                            return array_merge($option, ['val' => $option['key']]);
                                        },
                                        $params['offer_types']['widget_settings']
                                    ),
                                    'id' => 'key',
                                    'name' => 'name',
                                ],
                                'desc' => Main::translate(
                                    'Other payment methods (Installments 0%, Buy now, pay later, Installments for ' .
                                    'companies, Leasing) available after consulting a Comfino advisor ' .
                                    '(kontakt@comfino.pl).'
                                ),
                            ],
                            [
                                'type' => 'switch',
                                'label' => Main::translate('Show logos of financial services providers'),
                                'name' => 'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS',
                                'is_bool' => true,
                                'values' => [
                                    [
                                        'id' => 'provider_logos_enabled',
                                        'value' => true,
                                        'label' => Main::translate('Yes'),
                                    ],
                                    [
                                        'id' => 'provider_logos_disabled',
                                        'value' => false,
                                        'label' => Main::translate('No'),
                                    ],
                                ],
                            ],
                        ],
                        'submit' => [
                            'title' => Main::translate('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $formName,
                        ],
                    ]
                );

                $fields['widget_settings_advanced']['form'] = [
                    'legend' => ['title' => Main::translate('Advanced settings')],
                    'input' => [
                        [
                            'type' => 'text',
                            'label' => Main::translate('Widget price element selector'),
                            'name' => 'COMFINO_WIDGET_PRICE_SELECTOR',
                            'required' => false,
                        ],
                        [
                            'type' => 'text',
                            'label' => Main::translate('Widget price element attribute'),
                            'name' => 'COMFINO_WIDGET_PRICE_ATTRIBUTE',
                            'required' => false,
                            'desc' => Main::translate(
                                'Attribute of the price element holding the numeric price value. When set, the ' .
                                'widget reads the price from this attribute instead of parsing the element text, ' .
                                'which avoids a race with asynchronous price rendering. Leave empty to parse text.'
                            ),
                        ],
                        [
                            'type' => 'text',
                            'label' => Main::translate('Widget anchor element selector'),
                            'name' => 'COMFINO_WIDGET_TARGET_SELECTOR',
                            'required' => false,
                        ],
                        [
                            'type' => 'text',
                            'label' => Main::translate('Price change detection - container selector'),
                            'name' => 'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                            'required' => false,
                            'desc' => Main::translate(
                                'Selector of observed parent element which contains price element.'
                            ),
                        ],
                        [
                            'type' => 'text',
                            'label' => Main::translate('Price change detection - container hierarchy level'),
                            'name' => 'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                            'required' => false,
                            'desc' => Main::translate(
                                'Hierarchy level of observed parent element relative to the price element.'
                            ),
                        ],
                        [
                            'type' => 'select',
                            'label' => Main::translate('Embedding method'),
                            'name' => 'COMFINO_WIDGET_EMBED_METHOD',
                            'required' => false,
                            'options' => [
                                'query' => [
                                    ['key' => 'INSERT_INTO_FIRST', 'name' => 'INSERT_INTO_FIRST'],
                                    ['key' => 'INSERT_INTO_LAST', 'name' => 'INSERT_INTO_LAST'],
                                    ['key' => 'INSERT_BEFORE', 'name' => 'INSERT_BEFORE'],
                                    ['key' => 'INSERT_AFTER', 'name' => 'INSERT_AFTER'],
                                ],
                                'id' => 'key',
                                'name' => 'name',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'label' => Main::translate('Custom banner CSS style'),
                            'name' => 'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
                            'required' => false,
                            'desc' => Main::translate(
                                'URL for the custom banner style. Only links from your store domain are allowed.'
                            ),
                        ],
                        [
                            'type' => 'text',
                            'label' => Main::translate('Custom calculator CSS style'),
                            'name' => 'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
                            'required' => false,
                            'desc' => Main::translate(
                                'URL for the custom calculator style. Only links from your store domain are allowed.'
                            ),
                        ],
                    ],
                    'submit' => [
                        'title' => Main::translate('Save'),
                        'class' => 'btn btn-default pull-right',
                        'name' => $formName,
                    ],
                ];
                break;

            case 'developer_settings':
                $fields['developer_settings']['form'] = [];

                if (isset($params['messages'])) {
                    // Messages list in the form header (type => message): description, warning, success, error
                    $fields['developer_settings']['form'] = array_merge(
                        $fields['developer_settings']['form'],
                        $params['messages']
                    );
                }

                $fields['developer_settings']['form'] = array_merge(
                    $fields['developer_settings']['form'],
                    [
                        'input' => [
                            [
                                'type' => 'hidden',
                                'name' => 'active_tab',
                                'required' => false,
                            ],
                            [
                                'type' => 'switch',
                                'label' => Main::translate('Use test environment'),
                                'name' => 'COMFINO_IS_SANDBOX',
                                'is_bool' => true,
                                'values' => [
                                    [
                                        'id' => 'sandbox_enabled',
                                        'value' => true,
                                        'label' => Main::translate('Enabled'),
                                    ],
                                    [
                                        'id' => 'sandbox_disabled',
                                        'value' => false,
                                        'label' => Main::translate('Disabled'),
                                    ],
                                ],
                                'desc' => Main::translate(
                                    'The test environment allows the store owner to get acquainted with the ' .
                                    'functionality of the Comfino module. This is a Comfino simulator, thanks ' .
                                    'to which you can get to know all the advantages of this payment method. ' .
                                    'The use of the test mode is free (there are also no charges for orders).'
                                ),
                            ],
                            [
                                'type' => 'text',
                                'label' => Main::translate('Test environment API key'),
                                'name' => 'COMFINO_SANDBOX_API_KEY',
                                'required' => false,
                                'desc' => Main::translate(
                                    'Ask the supervisor for access to the test environment (key, login, password, ' .
                                    'link). Remember, the test key is different from the production key.'
                                ),
                            ],
                            [
                                'type' => 'switch',
                                'label' => Main::translate('Debug mode'),
                                'name' => 'COMFINO_DEBUG',
                                'is_bool' => true,
                                'values' => [
                                    [
                                        'id' => 'debug_enabled',
                                        'value' => true,
                                        'label' => Main::translate('Enabled'),
                                    ],
                                    [
                                        'id' => 'debug_disabled',
                                        'value' => false,
                                        'label' => Main::translate('Disabled'),
                                    ],
                                ],
                                'desc' => Main::translate(
                                    'Debug mode is useful in case of problems with Comfino payment availability. ' .
                                    'In this mode module logs details of internal process responsible for ' .
                                    'displaying of Comfino payment option at the payment methods list.'
                                ),
                            ],
                            [
                                'type' => 'switch',
                                'label' => Main::translate('Service mode'),
                                'name' => 'COMFINO_SERVICE_MODE',
                                'is_bool' => true,
                                'values' => [
                                    [
                                        'id' => 'service_mode_enabled',
                                        'value' => true,
                                        'label' => Main::translate('Enabled'),
                                    ],
                                    [
                                        'id' => 'service_mode_disabled',
                                        'value' => false,
                                        'label' => Main::translate('Disabled'),
                                    ],
                                ],
                                'desc' => Main::translate(
                                    'Service mode is useful in testing Comfino payment gateway without sharing ' .
                                    'it with customers. In this mode Comfino payment method is visible only for ' .
                                    'selected sessions and debug logs are collected only for these sessions.'
                                ),
                            ],
                        ],
                        'submit' => [
                            'title' => Main::translate('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $formName,
                        ],
                    ]
                );

                if (getenv('COMFINO_DEV_ENV') === 'TRUE') {
                    $fields['developer_settings']['form']['input'][] = [
                        'type' => 'switch',
                        'label' => Main::translate('Use development environment variables'),
                        'name' => 'COMFINO_DEV_ENV_VARS',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'dev_env_vars_enabled',
                                'value' => true,
                                'label' => Main::translate('Enabled'),
                            ],
                            [
                                'id' => 'dev_env_vars_disabled',
                                'value' => false,
                                'label' => Main::translate('Disabled'),
                            ],
                        ],
                        'desc' => Main::translate(
                            'Use of development environment variables with custom hosts which overwrite hosts stored ' .
                            'in the plugin.'
                        ),
                    ];
                }

                break;

            case 'plugin_diagnostics':
                $fields['plugin_diagnostics']['form'] = [];

                if (isset($params['messages'])) {
                    // Messages list in the form header (type => message): description, warning, success, error
                    $fields['plugin_diagnostics']['form'] = array_merge(
                        $fields['plugin_diagnostics']['form'],
                        $params['messages']
                    );
                }

                $fields['plugin_diagnostics']['form'] = array_merge(
                    $fields['plugin_diagnostics']['form'],
                    [
                        'input' => [
                            [
                                'type' => 'html',
                                'label' => Main::translate('Module reset'),
                                'name' => 'COMFINO_MODULE_RESET',
                                'html_content' => self::renderModuleResetSection(),
                            ],
                            [
                                'type' => 'html',
                                'label' => Main::translate('Errors log'),
                                'name' => 'COMFINO_WIDGET_ERRORS_LOG',
                                'html_content' => self::renderErrorLogSection(),
                            ],
                            [
                                'type' => 'html',
                                'label' => Main::translate('Debug log'),
                                'name' => 'COMFINO_DEBUG_LOG',
                                'required' => false,
                                'readonly' => true,
                                'html_content' => self::renderDebugLogSection(),
                            ],
                            [
                                'type' => 'html',
                                'label' => Main::translate('Installation logs'),
                                'name' => 'COMFINO_INSTALLATION_LOGS',
                                'required' => false,
                                'readonly' => true,
                                'html_content' => self::renderInstallationLogsSection(),
                            ],
                        ],
                    ]
                );
                break;

            default:
        }

        return $fields;
    }

    /**
     * @param int[] $productIds
     */
    private static function renderProductIdFilter(array $productIds): string
    {
        return TemplateManager::renderModuleView(
            'product-id-filter',
            'admin/_configure',
            ['product_ids' => implode(', ', $productIds)]
        );
    }

    /**
     * @param array $productTypes [typeCode => typeName, ...]
     * @param array $savedConfig  [typeCode => ['maxTerm' => ?int, 'minTerm' => ?int, 'terms' => ?int[]], ...]
     */
    private static function renderAllowedProductsConfig(array $productTypes, array $savedConfig): string
    {
        foreach ($savedConfig as &$entry) {
            $entry['termsStr'] = isset($entry['terms']) && is_array($entry['terms'])
                ? implode(',', $entry['terms'])
                : '';
        }

        unset($entry);

        return TemplateManager::renderModuleView(
            'allowed-products-config',
            'admin/_configure',
            [
                'product_types' => $productTypes,
                'saved_config' => $savedConfig,
            ]
        );
    }

    /**
     * @param int[] $selectedCategories
     */
    private static function renderCategoryTree(string $treeId, string $productType, array $selectedCategories): string
    {
        return TemplateManager::renderModuleView(
            'product-category-filter',
            'admin/_configure',
            [
                'tree_id' => $treeId,
                'tree_nodes' => json_encode(
                    self::buildCategoriesTree(self::getNestedCategories(), $selectedCategories)
                ),
                'close_depth' => 3,
                'product_type' => $productType,
            ]
        );
    }

    /**
     * @param int[] $selectedCategories
     */
    private static function buildCategoriesTree(array $categories, array $selectedCategories): array
    {
        $categoryTree = [];

        foreach ($categories as $category) {
            $treeNode = ['id' => (int) $category['id_category'], 'text' => $category['name']];

            if (isset($category['children'])) {
                $treeNode['children'] = self::buildCategoriesTree($category['children'], $selectedCategories);
            } elseif (in_array($treeNode['id'], $selectedCategories, true)) {
                $treeNode['checked'] = true;
            }

            $categoryTree[] = $treeNode;
        }

        return $categoryTree;
    }

    /**
     * Returns full category tree with nested categories.
     */
    private static function getNestedCategories(
        bool $leavesOnly = false,
        array $subCategories = [],
        int $position = 0
    ): ?array {
        static $categories = null;

        if ($categories === null) {
            $categories = \Category::getNestedCategories();
        }

        if ($leavesOnly) {
            $filteredCategories = [];
            $childCategories = [];

            foreach (count($subCategories) ? $subCategories : $categories as $category) {
                if (isset($category['children'])) {
                    $childCategories[] = self::getNestedCategories(
                        true, $category['children'], count($filteredCategories) + $position
                    );
                    $position += count($childCategories[count($childCategories) - 1]);
                } else {
                    $category['position'] += $position;
                    $filteredCategories[] = $category;
                }
            }

            $filteredCategories = array_merge($filteredCategories, ...$childCategories);

            usort(
                $filteredCategories,
                static function ($val1, $val2) { return $val1['position'] - $val2['position']; }
            );

            return $filteredCategories;
        }

        return $categories;
    }

    /**
     * Renders the module reset section with reset button.
     */
    private static function renderModuleResetSection(): string
    {
        return TemplateManager::renderModuleView(
            'module-reset',
            'admin/_configure',
            []
        );
    }

    /**
     * Renders the error log section with the textarea and clear the button.
     */
    private static function renderErrorLogSection(): string
    {
        return TemplateManager::renderModuleView(
            'error-log',
            'admin/_configure',
            ['error_log_content' => ErrorLogger::getLoggerInstance()->getErrorLog(self::ERROR_LOG_NUM_LINES)]
        );
    }

    /**
     * Renders the debug log section with a textarea and clear button.
     */
    private static function renderDebugLogSection(): string
    {
        return TemplateManager::renderModuleView(
            'debug-log',
            'admin/_configure',
            ['debug_log_content' => DebugLogger::getLoggerInstance()->getDebugLog(self::DEBUG_LOG_NUM_LINES)]
        );
    }

    /**
     * Renders the installation logs section (collapsed by default).
     *
     * @return string Rendered HTML content
     */
    private static function renderInstallationLogsSection(): string
    {
        return TemplateManager::renderModuleView(
            'installation-logs',
            'admin/_configure',
            [
                'install_log_content' => Main::readInstallLog(),
                'upgrade_log_content' => Main::readUpgradeLog(),
                'uninstall_log_content' => Main::readUninstallLog(),
            ]
        );
    }
}
