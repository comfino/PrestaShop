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
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

use Comfino\Main;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return bool
 */
function upgrade_module_4_3_0(Comfino $module)
{
    if (!$module->checkEnvironment()) {
        return false;
    }

    // Remove legacy config keys retired by the V3 paywall migration.
    Configuration::deleteByName('COMFINO_PAYWALL_URL');
    Configuration::deleteByName('COMFINO_SHOW_LOGO');

    // Remove legacy paywall-init JS files (replaced by the inline V3 SDK bootstrap in payment.tpl).
    @unlink(_PS_MODULE_DIR_ . $module->name . '/views/js/front/paywall-init.js');
    @unlink(_PS_MODULE_DIR_ . $module->name . '/views/js/front/paywall-init.min.js');

    // Initialize allowed-products-config keys (no restrictions by default; admin UI hidden until enabled).
    if (!Configuration::hasKey('COMFINO_ALLOWED_PRODUCTS_CONFIG')) {
        Configuration::updateValue('COMFINO_ALLOWED_PRODUCTS_CONFIG', null);
    }

    if (!Configuration::hasKey('COMFINO_ALLOWED_PRODUCTS_CONFIG_FORBIDDEN_PROD_TYPES')) {
        Configuration::updateValue(
            'COMFINO_ALLOWED_PRODUCTS_CONFIG_FORBIDDEN_PROD_TYPES',
            'BLIK,PAY_LATER,PAY_IN_PARTS,INSTANT_PAYMENTS'
        );
    }

    if (!Configuration::hasKey('COMFINO_ALLOWED_PRODUCTS_CONFIG_ENABLED')) {
        Configuration::updateValue('COMFINO_ALLOWED_PRODUCTS_CONFIG_ENABLED', false);
    }

    // Initialize paywall behavior keys added in 4.3.0.
    if (!Configuration::hasKey('COMFINO_PAYWALL_DIRECT_REDIRECT')) {
        Configuration::updateValue('COMFINO_PAYWALL_DIRECT_REDIRECT', false);
    }

    if (!Configuration::hasKey('COMFINO_PAYWALL_CUSTOM_CSS_URL')) {
        Configuration::updateValue('COMFINO_PAYWALL_CUSTOM_CSS_URL', '');
    }

    Main::updateUpgradeLog('Upgrade script for 4.3.0 executed.');

    return true;
}
