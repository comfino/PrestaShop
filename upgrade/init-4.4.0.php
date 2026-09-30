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

use Comfino\Configuration\ConfigManager;
use Comfino\Main;
use Comfino\Telemetry\ShopEnvironmentReporter;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return bool
 */
function upgrade_module_4_4_0(Comfino $module)
{
    if (!$module->checkEnvironment()) {
        return false;
    }

    /* Remove the legacy front controller deleted in 4.3.0 (replaced by the CDN-hosted product widget with inline JSON
       config). PrestaShop's module upgrade only overlays new files, it does not prune files removed from the package,
       so shops that upgraded straight to 4.3.0/4.3.1 kept this file on disk. Any leftover request against it fatals with
       "Call to undefined method Comfino\View\FrontendManager::renderWidgetInitCode()" since that method was renamed to
       renderWidgetConfigElement() in the same release that dropped the file. */
    @unlink(_PS_MODULE_DIR_ . $module->name . '/controllers/front/script.php');

    // Initialize paywall activity tracking consent added in 4.4.0 (opt-in, disabled by default).
    if (!Configuration::hasKey('COMFINO_PAYWALL_TRACKING_CONSENT')) {
        Configuration::updateValue('COMFINO_PAYWALL_TRACKING_CONSENT', false);
    }

    Main::updateUpgradeLog('Upgrade script for 4.4.0 executed.');

    // Report the shop environment to Comfino on upgrade (fire-and-forget).
    if (!empty(ConfigManager::getApiKey())) {
        ShopEnvironmentReporter::report();
    }

    return true;
}
