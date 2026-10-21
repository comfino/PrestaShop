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
require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';

/**
 * Renames the custom order states to the ComfinoPay brand and prepares the remote flags for the shop promotion.
 *
 * @param Comfino $module
 *
 * @return bool
 */
function upgrade_module_3_6_1($module)
{
    $config_manager = new \Comfino\ConfigManager($module);

    // The states are kept by their configuration IDs, so they are renamed in place - no duplicates are created.
    $config_manager->updateOrderStatuses();

    // A merchant's own payment text is kept - only the untouched old default follows the new brand.
    if ($config_manager->getConfigurationValue('COMFINO_PAYMENT_TEXT') === 'Comfino') {
        $config_manager->updateConfiguration(['COMFINO_PAYMENT_TEXT' => 'ComfinoPay'], false);
    }

    // Expired right away, so the first checkout after the upgrade fetches the flag attributes.
    Configuration::updateGlobalValue(\Comfino\ConfigManager::REMOTE_FLAG_ATTRIBUTES_EXPIRES_AT, 0);

    // The stored flag names and attributes go through the filters applied from this version on.
    $flag_attributes = \Comfino\ConfigManager::sanitizeRemoteFlagAttributes(
        json_decode((string) $config_manager->getConfigurationValue('COMFINO_REMOTE_FLAG_ATTRIBUTES'), true)
    );

    $config_manager->updateConfiguration(
        [
            'COMFINO_REMOTE_FLAGS' => $config_manager->getConfigurationValue('COMFINO_REMOTE_FLAGS'),
            'COMFINO_REMOTE_FLAG_ATTRIBUTES' => count($flag_attributes) ? (string) json_encode($flag_attributes) : '',
        ],
        false
    );

    /* Registers the cache invalidation URL at the backend, so promotion changes reach the shop without waiting for
       a merchant to open the module settings. Failures are not fatal - the daily token refresh retries it. */
    try {
        \Comfino\Api::init($module);
        \Comfino\Api::isShopAccountActive();
    } catch (Exception $e) {
        \Comfino\ErrorLogger::logError('Upgrade 3.6.1', 'Cache invalidation URL registration failed: ' . $e->getMessage());
    }

    return true;
}
