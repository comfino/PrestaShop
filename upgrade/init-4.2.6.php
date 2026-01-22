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

use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Main;
use Comfino\Order\ShopStatusManager;
use Comfino\PluginShared\CacheManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return bool
 */
function upgrade_module_4_2_6(Comfino $module)
{
    if (!$module->checkEnvironment()) {
        return false;
    }

    // Update default status map configuration.
    ConfigManager::updateConfiguration(
        ['COMFINO_STATUS_MAP' => json_encode(ShopStatusManager::DEFAULT_STATUS_MAP)],
        false
    );

    // Initialize not initialized options with default values - cumulative fix for configuration from older versions.
    ConfigManager::repairMissingConfigurationOptions();

    $module->registerHook('displayBackOfficeHeader');

    // Clear configuration and frontend cache.
    CacheManager::getCachePool()->clear();

    // Clear plugin logs.
    ErrorLogger::clearLogs();
    DebugLogger::clearLogs();

    Main::updateUpgradeLog('Upgrade script for 4.2.6 executed.');

    return true;
}
