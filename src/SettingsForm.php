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

class SettingsForm
{
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
                $prod_types = $params['offer_types']['sale_settings'];

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
    ): string
    {
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
    ): ?array
    {
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
