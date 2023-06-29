<?php
/**
 * 2007-2023 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use Comfino\Api;
use Comfino\ConfigManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

class ComfinoScriptModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        parent::postProcess();

        header('Content-Type: application/javascript');

        $config_manager = new ConfigManager();

        if ($config_manager->getConfigurationValue('COMFINO_WIDGET_ENABLED')) {
            echo str_replace(
                [
                    '{WIDGET_KEY}',
                    '{WIDGET_PRICE_SELECTOR}',
                    '{WIDGET_TARGET_SELECTOR}',
                    '{WIDGET_PRICE_OBSERVER_SELECTOR}',
                    '{WIDGET_PRICE_OBSERVER_LEVEL}',
                    '{WIDGET_TYPE}',
                    '{OFFER_TYPE}',
                    '{EMBED_METHOD}',
                    '{WIDGET_SCRIPT_URL}',
                ],
                array_merge(
                    $config_manager->getConfigurationValues(
                        'widget_settings',
                        [
                            'COMFINO_WIDGET_KEY',
                            'COMFINO_WIDGET_PRICE_SELECTOR',
                            'COMFINO_WIDGET_TARGET_SELECTOR',
                            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                            'COMFINO_WIDGET_TYPE',
                            'COMFINO_WIDGET_OFFER_TYPE',
                            'COMFINO_WIDGET_EMBED_METHOD',
                        ]
                    ),
                    [Api::getWidgetScriptUrl()]
                ),
                $config_manager->getConfigurationValue('COMFINO_WIDGET_CODE')
            );
        }

        exit;
    }
}
