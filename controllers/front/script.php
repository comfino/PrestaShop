<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

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

        if (Configuration::get('COMFINO_WIDGET_ENABLED')) {
            echo str_replace(
                [
                    '{WIDGET_KEY}',
                    '{WIDGET_PRICE_SELECTOR}',
                    '{WIDGET_TARGET_SELECTOR}',
                    '{WIDGET_TYPE}',
                    '{OFFER_TYPE}',
                    '{EMBED_METHOD}',
                    '{PRICE_OBSERVER_LEVEL}',
                    '{WIDGET_SCRIPT_URL}',
                ],
                [
                    Configuration::get('COMFINO_WIDGET_KEY'),
                    Configuration::get('COMFINO_WIDGET_PRICE_SELECTOR'),
                    Configuration::get('COMFINO_WIDGET_TARGET_SELECTOR'),
                    Configuration::get('COMFINO_WIDGET_TYPE'),
                    Configuration::get('COMFINO_WIDGET_OFFER_TYPE'),
                    Configuration::get('COMFINO_WIDGET_EMBED_METHOD'),
                    Configuration::get('COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'),
                    Configuration::get('COMFINO_IS_SANDBOX')
                        ? Comfino::WIDGET_SCRIPT_SANDBOX_URL
                        : Comfino::WIDGET_SCRIPT_PRODUCTION_URL,
                ],
                Configuration::get('COMFINO_WIDGET_CODE')
            );
        }

        exit;
    }
}
