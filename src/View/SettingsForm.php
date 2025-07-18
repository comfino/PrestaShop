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
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\PluginShared\CacheManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SettingsForm
{
    public const ERROR_LOG_NUM_LINES = 100;
    public const DEBUG_LOG_NUM_LINES = 200;
    public const COMFINO_SUPPORT_EMAIL = 'pomoc@comfino.pl';
    public const COMFINO_SUPPORT_PHONE = '887-106-027';

    public static function processForm(\PaymentModule $module): array
    {
        ErrorLogger::init();

        $activeTab = 'payment_settings';
        $outputType = 'success';
        $output = [];
        $widgetKeyError = false;
        $widgetKey = ConfigManager::getConfigurationValue('COMFINO_WIDGET_KEY', '');

        $errorEmptyMsg = $module->l("Field '%s' can not be empty.");
        $errorNumericFormatMsg = $module->l("Field '%s' has wrong numeric format.");

        $configurationOptions = [];

        if (\Tools::isSubmit('submit_configuration')) {
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
                            $output[] = sprintf($errorEmptyMsg, $module->l('Production environment API key'));
                        }
                        if (\Tools::isEmpty(\Tools::getValue('COMFINO_PAYMENT_TEXT'))) {
                            $output[] = sprintf($errorEmptyMsg, $module->l('Payment text'));
                        }
                        if (\Tools::isEmpty(\Tools::getValue('COMFINO_MINIMAL_CART_AMOUNT'))) {
                            $output[] = sprintf($errorEmptyMsg, $module->l('Minimal amount in cart'));
                        } elseif (!is_numeric(\Tools::getValue('COMFINO_MINIMAL_CART_AMOUNT'))) {
                            $output[] = sprintf($errorNumericFormatMsg, $module->l('Minimal amount in cart'));
                        }
                    } else {
                        $sandboxMode = (bool) \Tools::getValue('COMFINO_IS_SANDBOX');
                        $apiKey = $sandboxMode
                            ? \Tools::getValue('COMFINO_SANDBOX_API_KEY')
                            : ConfigManager::getConfigurationValue('COMFINO_API_KEY');
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
                                    ' settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                    $e
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
                            $output[] = sprintf($module->l('API key %s is not valid.'), $apiKey);

                            if (!empty(getenv('COMFINO_DEV'))) {
                                $output[] = sprintf('Comfino API host: %s', $apiClient->getApiHost());
                            }
                        } catch (\Throwable $e) {
                            ApiClient::processApiError(
                                ($activeTab === 'payment_settings' ? 'Payment' : 'Developer') .
                                ' settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                $e
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
                    break;

                case 'widget_settings':
                    if (!is_numeric(\Tools::getValue('COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'))) {
                        $output[] = sprintf(
                            $errorNumericFormatMsg,
                            $module->l('Price change detection - container hierarchy level')
                        );
                    }

                    $customCssUrlOptionNames = [
                        'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
                        'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
                    ];

                    foreach ($customCssUrlOptionNames as $customCssUrlOptionName) {
                        if (!empty($customCssUrl = \Tools::getValue($customCssUrlOptionName))) {
                            if (!\Validate::isUrl($customCssUrl)) {
                                $output[] = sprintf($module->l('Custom CSS URL "%s" is not valid.'), $customCssUrl);
                            } elseif (!\Validate::isAbsoluteUrl($customCssUrl)) {
                                $output[] = sprintf($module->l('Custom CSS URL "%s" is not absolute.'), $customCssUrl);
                            } elseif (stripos($customCssUrl, \Tools::getShopDomain()) === false) {
                                $output[] = sprintf(
                                    $module->l('Custom CSS URL "%s" is not in shop domain "%s".'),
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
                                    'Widget settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                    $e
                                );

                                $output[] = $e->getMessage();
                                $outputType = 'error';
                                $widgetKeyError = true;
                            }
                        } catch (AuthorizationError|AccessDenied $e) {
                            $outputType = 'warning';
                            $output[] = sprintf($module->l('API key %s is not valid.'), $apiKey);
                        } catch (\Throwable $e) {
                            ApiClient::processApiError(
                                'Widget settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                $e
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
                $output[] = $module->l('Settings not updated.');
            } else {
                // Update plugin configuration.
                ConfigManager::updateConfiguration($configurationOptions, false);

                $output[] = $module->l('Settings updated.');
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
                $module->l('PrestaShop %s Comfino %s - question'),
                _PS_VERSION_,
                COMFINO_VERSION
            ),
            'support_email_body' => sprintf(
                'PrestaShop %s Comfino %s, PHP %s',
                _PS_VERSION_,
                COMFINO_VERSION,
                PHP_VERSION
            ),
            'contact_msg1' => $module->l('Do you want to ask about something? Write to us at'),
            'contact_msg2' => sprintf(
                $module->l(
                    'or contact us by phone. We are waiting on the number: %s. We will answer all your questions!'
                ),
                self::COMFINO_SUPPORT_PHONE
            ),
            'plugin_version' => $module->version,
        ];
    }

    public static function getFormFields(\PaymentModule $module, array $params): array
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
                                'label' => $module->l('Production environment API key'),
                                'name' => 'COMFINO_API_KEY',
                                'required' => true,
                                'placeholder' => $module->l('Please enter the key provided during registration'),
                            ],
                            [
                                'type' => 'text',
                                'label' => $module->l('Payment text'),
                                'name' => 'COMFINO_PAYMENT_TEXT',
                                'required' => true,
                            ],
                            [
                                'type' => 'text',
                                'label' => $module->l('Minimal amount in cart'),
                                'name' => 'COMFINO_MINIMAL_CART_AMOUNT',
                                'required' => true,
                            ],
                        ],
                        'submit' => [
                            'title' => $module->l('Save'),
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
                            $module,
                            'product_categories',
                            $prodTypeCode,
                            $selectedCategories
                        ),
                    ];
                }

                $fields['sale_settings_category_filter']['form'] = [
                    'legend' => ['title' => $module->l('Rules for the availability of financial products')],
                    'input' => $productCategoryFilterInputs,
                    'submit' => [
                        'title' => $module->l('Save'),
                        'class' => 'btn btn-default pull-right',
                        'name' => $formName,
                    ],
                ];
                break;

            case 'widget_settings':
                $fields['widget_settings_basic']['form'] = ['legend' => ['title' => $module->l('Basic settings')]];

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
                                'label' => $module->l('Widget is active?'),
                                'name' => 'COMFINO_WIDGET_ENABLED',
                                'values' => [
                                    [
                                        'id' => 'widget_enabled',
                                        'value' => true,
                                        'label' => $module->l('Enabled'),
                                    ],
                                    [
                                        'id' => 'widget_disabled',
                                        'value' => false,
                                        'label' => $module->l('Disabled'),
                                    ],
                                ],
                            ],
                            [
                                'type' => 'hidden',
                                'label' => $module->l('Widget key'),
                                'name' => 'COMFINO_WIDGET_KEY',
                                'required' => false,
                            ],
                            [
                                'type' => 'select',
                                'label' => $module->l('Widget type'),
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
                                'label' => $module->l('Offer types'),
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
                                'desc' => $module->l(
                                    'Other payment methods (Installments 0%, Buy now, pay later, Installments for ' .
                                    'companies, Leasing) available after consulting a Comfino advisor ' .
                                    '(kontakt@comfino.pl).'
                                ),
                            ],
                            [
                                'type' => 'switch',
                                'label' => $module->l('Show logos of financial services providers'),
                                'name' => 'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS',
                                'values' => [
                                    [
                                        'id' => 'provider_logos_enabled',
                                        'value' => true,
                                        'label' => $module->l('Yes'),
                                    ],
                                    [
                                        'id' => 'provider_logos_disabled',
                                        'value' => false,
                                        'label' => $module->l('No'),
                                    ],
                                ],
                            ],
                        ],
                        'submit' => [
                            'title' => $module->l('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $formName,
                        ],
                    ]
                );

                $fields['widget_settings_advanced']['form'] = [
                    'legend' => ['title' => $module->l('Advanced settings')],
                    'input' => [
                        [
                            'type' => 'text',
                            'label' => $module->l('Widget price element selector'),
                            'name' => 'COMFINO_WIDGET_PRICE_SELECTOR',
                            'required' => false,
                        ],
                        [
                            'type' => 'text',
                            'label' => $module->l('Widget anchor element selector'),
                            'name' => 'COMFINO_WIDGET_TARGET_SELECTOR',
                            'required' => false,
                        ],
                        [
                            'type' => 'text',
                            'label' => $module->l('Price change detection - container selector'),
                            'name' => 'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                            'required' => false,
                            'desc' => $module->l(
                                'Selector of observed parent element which contains price element.'
                            ),
                        ],
                        [
                            'type' => 'text',
                            'label' => $module->l('Price change detection - container hierarchy level'),
                            'name' => 'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                            'required' => false,
                            'desc' => $module->l(
                                'Hierarchy level of observed parent element relative to the price element.'
                            ),
                        ],
                        [
                            'type' => 'select',
                            'label' => $module->l('Embedding method'),
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
                            'label' => $module->l('Custom banner CSS style'),
                            'name' => 'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
                            'required' => false,
                            'desc' => $module->l(
                                'URL for the custom banner style. Only links from your store domain are allowed.'
                            ),
                        ],
                        [
                            'type' => 'text',
                            'label' => $module->l('Custom calculator CSS style'),
                            'name' => 'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
                            'required' => false,
                            'desc' => $module->l(
                                'URL for the custom calculator style. Only links from your store domain are allowed.'
                            ),
                        ],
                        [
                            'type' => 'textarea',
                            'label' => $module->l('Widget initialization code'),
                            'name' => 'COMFINO_WIDGET_CODE',
                            'required' => false,
                            'rows' => 15,
                            'cols' => 60,
                        ],
                    ],
                    'submit' => [
                        'title' => $module->l('Save'),
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
                                'label' => $module->l('Use test environment'),
                                'name' => 'COMFINO_IS_SANDBOX',
                                'values' => [
                                    [
                                        'id' => 'sandbox_enabled',
                                        'value' => true,
                                        'label' => $module->l('Enabled'),
                                    ],
                                    [
                                        'id' => 'sandbox_disabled',
                                        'value' => false,
                                        'label' => $module->l('Disabled'),
                                    ],
                                ],
                                'desc' => $module->l(
                                    'The test environment allows the store owner to get acquainted with the ' .
                                    'functionality of the Comfino module. This is a Comfino simulator, thanks ' .
                                    'to which you can get to know all the advantages of this payment method. ' .
                                    'The use of the test mode is free (there are also no charges for orders).'
                                ),
                            ],
                            [
                                'type' => 'text',
                                'label' => $module->l('Test environment API key'),
                                'name' => 'COMFINO_SANDBOX_API_KEY',
                                'required' => false,
                                'desc' => $module->l(
                                    'Ask the supervisor for access to the test environment (key, login, password, ' .
                                    'link). Remember, the test key is different from the production key.'
                                ),
                            ],
                            [
                                'type' => 'switch',
                                'label' => $module->l('Debug mode'),
                                'name' => 'COMFINO_DEBUG',
                                'values' => [
                                    [
                                        'id' => 'debug_enabled',
                                        'value' => true,
                                        'label' => $module->l('Enabled'),
                                    ],
                                    [
                                        'id' => 'debug_disabled',
                                        'value' => false,
                                        'label' => $module->l('Disabled'),
                                    ],
                                ],
                                'desc' => $module->l(
                                    'Debug mode is useful in case of problems with Comfino payment availability. ' .
                                    'In this mode module logs details of internal process responsible for ' .
                                    'displaying of Comfino payment option at the payment methods list.'
                                ),
                            ],
                            [
                                'type' => 'switch',
                                'label' => $module->l('Service mode'),
                                'name' => 'COMFINO_SERVICE_MODE',
                                'values' => [
                                    [
                                        'id' => 'service_mode_enabled',
                                        'value' => true,
                                        'label' => $module->l('Enabled'),
                                    ],
                                    [
                                        'id' => 'service_mode_disabled',
                                        'value' => false,
                                        'label' => $module->l('Disabled'),
                                    ],
                                ],
                                'desc' => $module->l(
                                    'Service mode is useful in testing Comfino payment gateway without sharing ' .
                                    'it with customers. In this mode Comfino payment method is visible only for ' .
                                    'selected sessions and debug logs are collected only for these sessions.'
                                ),
                            ],
                        ],
                        'submit' => [
                            'title' => $module->l('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $formName,
                        ],
                    ]
                );
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
                                'label' => $module->l('Errors log'),
                                'name' => 'COMFINO_WIDGET_ERRORS_LOG',
                                'html_content' =>
                                    '<textarea rows="20" cols="60" readonly="readonly">' .
                                    ErrorLogger::getLoggerInstance()->getErrorLog(self::ERROR_LOG_NUM_LINES) .
                                    '</textarea>',
                            ],
                            [
                                'type' => 'html',
                                'label' => $module->l('Debug log'),
                                'name' => 'COMFINO_DEBUG_LOG',
                                'required' => false,
                                'readonly' => true,
                                'html_content' =>
                                    '<textarea rows="40" cols="60" readonly="readonly">' .
                                    DebugLogger::getLoggerInstance()->getDebugLog(self::DEBUG_LOG_NUM_LINES) .
                                    '</textarea>',
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
     * @param int[] $selectedCategories
     */
    private static function renderCategoryTree(
        \PaymentModule $module,
        string $treeId,
        string $productType,
        array $selectedCategories
    ): string {
        return TemplateManager::renderModuleView(
            $module,
            'product_category_filter',
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
}
