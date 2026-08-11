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

require_once _PS_MODULE_DIR_ . 'comfino/src/ConfigManager.php';

/**
 * Migrates the shop from the deprecated UMD paywall/widget integration to the frontend SDK.
 *
 * @param Comfino $module
 *
 * @return bool
 */
function upgrade_module_3_6_0($module)
{
    $config_manager = new \Comfino\ConfigManager($module);

    // Initialize configuration options added together with the frontend SDK integration.
    $config_manager->updateConfiguration(
        [
            'COMFINO_ERROR_LOGGING_ACCESS_TOKEN' => '',
            'COMFINO_ERROR_LOGGING_ACCESS_TOKEN_EXPIRES_AT' => 0,
            'COMFINO_REMOTE_FLAGS' => '',
            'COMFINO_REMOTE_FLAG_ATTRIBUTES' => '',
            'COMFINO_PAYMENT_TEXT_ENABLED' => true,
            'COMFINO_CHECKOUT_PRODUCT_TYPES' => '',
        ],
        false
    );

    /* Drop the manually editable widget initialization code and the widget script version overrides - the
       widget is now bootstrapped by a bridge script served from the SDK CDN. */
    $config_manager->deleteObsoleteConfigurationValues();

    return true;
}