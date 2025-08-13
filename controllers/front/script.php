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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ConfigManager.php';
require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

use Comfino\Api;
use Comfino\ConfigManager;

class ComfinoScriptModuleFrontController extends ModuleFrontController
{
    const WIDGET_INIT_PARAMS = [
        'WIDGET_KEY',
        'WIDGET_PRICE_SELECTOR',
        'WIDGET_TARGET_SELECTOR',
        'WIDGET_PRICE_OBSERVER_SELECTOR',
        'WIDGET_PRICE_OBSERVER_LEVEL',
        'WIDGET_TYPE',
        'OFFER_TYPES',
        'EMBED_METHOD',
        'SHOW_PROVIDER_LOGOS',
        'CUSTOM_BANNER_CSS_URL',
        'CUSTOM_CALCULATOR_CSS_URL',
    ];

    const WIDGET_INIT_VARIABLES = [
        'WIDGET_SCRIPT_URL',
        'PRODUCT_ID',
        'PRODUCT_PRICE',
        'PLATFORM',
        'PLATFORM_VERSION',
        'PLATFORM_DOMAIN',
        'PLUGIN_VERSION',
        'AVAILABLE_PRODUCT_TYPES',
        'PRODUCT_CART_DETAILS',
        'LANGUAGE',
        'CURRENCY',
    ];

    public function postProcess()
    {
        Api::init($this->module);

        parent::postProcess();

        header('Content-Type: application/javascript');

        $config_manager = new ConfigManager($this->module);

        if ($config_manager->getConfigurationValue('COMFINO_WIDGET_ENABLED')) {
            $product_id = Tools::getValue('product_id', null);

            try {
                $response = $this->renderWidgetInitScript(
                    $config_manager->getCurrentWidgetCode($product_id),
                    array_combine(
                        [
                            'WIDGET_KEY',
                            'WIDGET_PRICE_SELECTOR',
                            'WIDGET_TARGET_SELECTOR',
                            'WIDGET_PRICE_OBSERVER_SELECTOR',
                            'WIDGET_PRICE_OBSERVER_LEVEL',
                            'WIDGET_TYPE',
                            'OFFER_TYPES',
                            'EMBED_METHOD',
                            'SHOW_PROVIDER_LOGOS',
                            'CUSTOM_BANNER_CSS_URL',
                            'CUSTOM_CALCULATOR_CSS_URL',
                        ],
                        $config_manager->getConfigurationValues(
                            'widget_settings',
                            [
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
                            ]
                        )
                    ),
                    $config_manager->getWidgetVariables($product_id)
                );
            } catch (\Exception $e) {
                $response = '';
            }
        } else {
            $response = '';
        }

        exit($response);
    }

    /**
     * @param string $widget_init_code
     * @param array $widget_init_params
     * @param array $widget_init_variables
     *
     * @return string
     */
    private function renderWidgetInitScript($widget_init_code, $widget_init_params, $widget_init_variables)
    {
        $widget_init_params_assoc_keys = array_flip(self::WIDGET_INIT_PARAMS);
        $widget_init_variables_assoc_keys = array_flip(self::WIDGET_INIT_VARIABLES);

        if (count(array_intersect_key($widget_init_params_assoc_keys, $widget_init_params)) !==
            count(self::WIDGET_INIT_PARAMS)
        ) {
            throw new \InvalidArgumentException('Invalid widget initialization parameters.');
        }

        if (count(array_intersect_key($widget_init_variables_assoc_keys, $widget_init_variables)) !==
            count(self::WIDGET_INIT_VARIABLES)
        ) {
            throw new \InvalidArgumentException('Invalid widget initialization variables.');
        }

        if (!is_array($widget_init_params['OFFER_TYPES'])) {
            $widget_init_params['OFFER_TYPES'] = explode(',', $widget_init_params['OFFER_TYPES']);
        }

        return str_replace(
            array_merge(
                array_map(
                    static function ($widget_init_param_name) {
                        return '{' . $widget_init_param_name . '}';
                    },
                    array_merge(self::WIDGET_INIT_PARAMS, array_keys($widget_init_variables))
                ),
                ["'true'", "'false'", "'null'"]
            ),
            array_merge(
                array_map(
                    static function ($var_value) {
                        if (is_bool($var_value)) {
                            return $var_value ? 'true' : 'false';
                        }

                        if (is_array($var_value)) {
                            return ($result = json_encode($var_value)) !== false ? $result : '[]';
                        }

                        return $var_value !== null ? (string) $var_value : 'null';
                    },
                    array_merge(
                        array_merge($widget_init_params_assoc_keys, $widget_init_params),
                        array_values($widget_init_variables)
                    )
                ),
                ['true', 'false', 'null']
            ),
            $widget_init_code
        );
    }
}
