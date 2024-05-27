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

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SettingsForm
{
    private const COMFINO_SUPPORT_EMAIL = 'pomoc@comfino.pl';
    private const COMFINO_SUPPORT_PHONE = '887-106-027';

    public static function processForm(\PaymentModule $module): array
    {
        ErrorLogger::init($module);

        $active_tab = 'payment_settings';
        $output_type = 'success';
        $output = [];
        $widget_key_error = false;
        $widget_key = '';

        $error_empty_msg = $module->l("Field '%s' can not be empty.");
        $error_numeric_format_msg = $module->l("Field '%s' has wrong numeric format.");

        $configuration_options = [];

        if (\Tools::isSubmit('submit_configuration')) {
            $active_tab = \Tools::getValue('active_tab');

            foreach (ConfigManager::CONFIG_OPTIONS[$active_tab] as $option_name) {
                if ($option_name !== 'COMFINO_WIDGET_KEY') {
                    $configuration_options[$option_name] = \Tools::getValue($option_name);
                }
            }

            switch ($active_tab) {
                case 'payment_settings':
                case 'developer_settings':
                    if ($active_tab === 'payment_settings') {
                        $sandbox_mode = ConfigManager::isSandboxMode();
                        $api_key = $sandbox_mode
                            ? ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY')
                            : \Tools::getValue('COMFINO_API_KEY');

                        if (\Tools::isEmpty(\Tools::getValue('COMFINO_API_KEY'))) {
                            $output[] = sprintf($error_empty_msg, $module->l('Production environment API key'));
                        }
                        if (\Tools::isEmpty(\Tools::getValue('COMFINO_PAYMENT_TEXT'))) {
                            $output[] = sprintf($error_empty_msg, $module->l('Payment text'));
                        }
                        if (\Tools::isEmpty(\Tools::getValue('COMFINO_MINIMAL_CART_AMOUNT'))) {
                            $output[] = sprintf($error_empty_msg, $module->l('Minimal amount in cart'));
                        } elseif (!is_numeric(\Tools::getValue('COMFINO_MINIMAL_CART_AMOUNT'))) {
                            $output[] = sprintf($error_numeric_format_msg, $module->l('Minimal amount in cart'));
                        }
                    } else {
                        $sandbox_mode = (bool) \Tools::getValue('COMFINO_IS_SANDBOX');
                        $api_key = $sandbox_mode
                            ? \Tools::getValue('COMFINO_SANDBOX_API_KEY')
                            : ConfigManager::getConfigurationValue('COMFINO_API_KEY');
                    }

                    if (!empty($api_key) && !count($output)) {
                        try {
                            // Check if passed API key is valid.
                            ApiClient::getInstance($sandbox_mode, $api_key)->isShopAccountActive();

                            try {
                                // If API key is valid fetch widget key from API endpoint.
                                $widget_key = ApiClient::getInstance($sandbox_mode, $api_key)->getWidgetKey();
                            } catch (\Throwable $e) {
                                ApiClient::processApiError(
                                    ($active_tab === 'payment_settings' ? 'Payment' : 'Developer') .
                                    ' settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                    $e
                                );

                                $output[] = $e->getMessage();
                                $output_type = 'error';
                                $widget_key_error = true;
                            }
                        } catch (AuthorizationError|AccessDenied $e) {
                            $output_type = 'warning';
                            $output[] = sprintf($module->l('API key %s is not valid.'), $api_key);
                        } catch (\Throwable $e) {
                            ApiClient::processApiError(
                                ($active_tab === 'payment_settings' ? 'Payment' : 'Developer') .
                                ' settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                $e
                            );

                            $output_type = 'error';
                            $output[] = $e->getMessage();
                        }
                    }

                    $configuration_options['COMFINO_WIDGET_KEY'] = $widget_key;
                    break;

                case 'sale_settings':
                    $product_categories = array_keys(ConfigManager::getAllProductCategories());
                    $product_category_filters = [];

                    foreach (\Tools::getValue('product_categories') as $product_type => $category_ids) {
                        $product_category_filters[$product_type] = array_values(array_diff(
                            $product_categories,
                            explode(',', $category_ids)
                        ));
                    }

                    $configuration_options['COMFINO_PRODUCT_CATEGORY_FILTERS'] = $product_category_filters;
                    break;

                case 'widget_settings':
                    if (!is_numeric(\Tools::getValue('COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'))) {
                        $output[] = sprintf(
                            $error_numeric_format_msg,
                            $module->l('Price change detection - container hierarchy level')
                        );
                    }

                    if (!count($output) && !empty($api_key = ConfigManager::getApiKey())) {
                        // Update widget key.
                        try {
                            // Check if passed API key is valid.
                            ApiClient::getInstance()->isShopAccountActive();

                            try {
                                $widget_key = ApiClient::getInstance()->getWidgetKey();
                            } catch (\Throwable $e) {
                                ApiClient::processApiError(
                                    'Widget settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                    $e
                                );

                                $output[] = $e->getMessage();
                                $output_type = 'error';
                                $widget_key_error = true;
                            }
                        } catch (AuthorizationError|AccessDenied $e) {
                            $output_type = 'warning';
                            $output[] = sprintf($module->l('API key %s is not valid.'), $api_key);
                        } catch (\Throwable $e) {
                            ApiClient::processApiError(
                                'Widget settings error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                                $e
                            );

                            $output_type = 'error';
                            $output[] = $e->getMessage();
                        }
                    }

                    $configuration_options['COMFINO_WIDGET_KEY'] = $widget_key;
                    break;
            }

            if (!$widget_key_error && count($output)) {
                $output_type = 'warning';
                $output[] = $module->l('Settings not updated.');
            } else {
                // Update plugin configuration.
                ConfigManager::updateConfiguration($configuration_options, false);

                $output[] = $module->l('Settings updated.');
            }
        }

        return [
            'active_tab' => $active_tab,
            'output' => $output,
            'output_type' => $output_type,
            'logo_url' => ApiClient::getLogoUrl(),
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
        $config_tab = $params['config_tab'] ?? '';
        $form_name = $params['form_name'] ?? 'submit_configuration';

        switch ($config_tab) {
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
                            'name' => $form_name,
                        ],
                    ]
                );
                break;

            case 'sale_settings':
                $product_categories = ConfigManager::getAllProductCategories();
                $product_category_filters = SettingsManager::getProductCategoryFilters();

                $product_category_filter_inputs = [
                    [
                        'type' => 'hidden',
                        'name' => 'active_tab',
                        'required' => false,
                    ],
                ];

                foreach (SettingsManager::getCatFilterAvailProdTypes() as $prod_type_code => $prod_type_name) {
                    $product_category_filter_inputs[] = [
                        'type' => 'html',
                        'name' => $prod_type_code . '_label',
                        'required' => false,
                        'html_content' => '<h3>' . $prod_type_name . '</h3>',
                    ];

                    if (isset($product_category_filters[$prod_type_code])) {
                        $selected_categories = array_diff(
                            array_keys($product_categories),
                            $product_category_filters[$prod_type_code]
                        );
                    } else {
                        $selected_categories = array_keys($product_categories);
                    }

                    $product_category_filter_inputs[] = [
                        'type' => 'html',
                        'name' => 'product_category_filter[' . $prod_type_code . ']',
                        'required' => false,
                        'html_content' => self::renderCategoryTree(
                            $module,
                            'product_categories',
                            $prod_type_code,
                            $selected_categories
                        ),
                    ];
                }

                $fields['sale_settings_category_filter']['form'] = [
                    'legend' => ['title' => $module->l('Rules for the availability of financial products')],
                    'input' => $product_category_filter_inputs,
                    'submit' => [
                        'title' => $module->l('Save'),
                        'class' => 'btn btn-default pull-right',
                        'name' => $form_name,
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
                                'type' => 'select',
                                'label' => $module->l('Offer type'),
                                'name' => 'COMFINO_WIDGET_OFFER_TYPE',
                                'required' => false,
                                'options' => [
                                    'query' => $params['offer_types']['widget_settings'],
                                    'id' => 'key',
                                    'name' => 'name',
                                ],
                                'desc' => $module->l(
                                    'Other payment methods (Installments 0%, Buy now, pay later, Installments for ' .
                                    'Companies) available after consulting a Comfino advisor (kontakt@comfino.pl).'
                                ),
                            ],
                        ],
                        'submit' => [
                            'title' => $module->l('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $form_name,
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
                        'name' => $form_name,
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
                        ],
                        'submit' => [
                            'title' => $module->l('Save'),
                            'class' => 'btn btn-default pull-right',
                            'name' => $form_name,
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
                                'type' => 'textarea',
                                'label' => $module->l('Errors log'),
                                'name' => 'COMFINO_WIDGET_ERRORS_LOG',
                                'required' => false,
                                'readonly' => true,
                                'rows' => 20,
                                'cols' => 60,
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
     * @param int[] $selected_categories
     */
    private static function renderCategoryTree(
        \PaymentModule $module,
        string $tree_id,
        string $product_type,
        array $selected_categories
    ): string {
        return TemplateManager::render(
            $module,
            'product_category_filter',
            'admin/_configure',
            [
                'tree_id' => $tree_id,
                'tree_nodes' => json_encode(
                    self::buildCategoriesTree(self::getNestedCategories(), $selected_categories)
                ),
                'close_depth' => 3,
                'product_type' => $product_type,
            ]
        );
    }

    /**
     * @param int[] $selected_categories
     */
    private static function buildCategoriesTree(array $categories, array $selected_categories): array
    {
        $cat_tree = [];

        foreach ($categories as $category) {
            $tree_node = ['id' => (int) $category['id_category'], 'text' => $category['name']];

            if (isset($category['children'])) {
                $tree_node['children'] = self::buildCategoriesTree($category['children'], $selected_categories);
            } elseif (in_array($tree_node['id'], $selected_categories, true)) {
                $tree_node['checked'] = true;
            }

            $cat_tree[] = $tree_node;
        }

        return $cat_tree;
    }

    private static function getNestedCategories(
        bool $leafs_only = false,
        array $sub_categories = [],
        int $position = 0
    ): ?array {
        static $categories = null;

        if ($categories === null) {
            $categories = \Category::getNestedCategories();
        }

        if ($leafs_only) {
            $filtered_categories = [];
            $child_categories = [];

            foreach (count($sub_categories) ? $sub_categories : $categories as $category) {
                if (isset($category['children'])) {
                    $child_categories[] = self::getNestedCategories(
                        true, $category['children'], count($filtered_categories) + $position
                    );
                    $position += count($child_categories[count($child_categories) - 1]);
                } else {
                    $category['position'] += $position;
                    $filtered_categories[] = $category;
                }
            }

            $filtered_categories = array_merge($filtered_categories, ...$child_categories);

            usort(
                $filtered_categories,
                static function ($val1, $val2) { return $val1['position'] - $val2['position']; }
            );

            return $filtered_categories;
        }

        return $categories;
    }
}
