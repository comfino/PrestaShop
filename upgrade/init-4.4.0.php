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
use Comfino\Order\ShopStatusManager;
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

    /* The update notice is now dismissed through the authenticated settings page; remove the unauthenticated controller. */
    @unlink(_PS_MODULE_DIR_ . $module->name . '/controllers/front/updatedismiss.php');

    // Rename the existing custom order statuses in place: "(Comfino)" -> "(ComfinoPay)".
    ShopStatusManager::updateCustomOrderStatuses($module);

    /* Default payment title rebrand: only the untouched old default is rewritten, per shop scope. A merchant's own text
       is never touched. */
    foreach (array_merge([0], Shop::getShops(false, null, true) ?: []) as $shopId) {
        $groupId = $shopId ? (int) Shop::getGroupFromShop((int) $shopId) : null;
        $shopId = $shopId ? (int) $shopId : null;

        if (Configuration::hasKey('COMFINO_PAYMENT_TEXT', null, $groupId, $shopId) &&
            Configuration::get('COMFINO_PAYMENT_TEXT', null, $groupId, $shopId) === 'Comfino'
        ) {
            Configuration::updateValue('COMFINO_PAYMENT_TEXT', 'ComfinoPay', false, $groupId, $shopId);
        }
    }

    // Move the logs out of the module directory (var/log was reachable over HTTP on some server setups).
    Main::migrateLegacyLogs();

    // Initialize paywall activity tracking consent (opt-in, disabled by default; the admin switch is hidden until 4.5.0).
    if (!Configuration::hasKey('COMFINO_PAYWALL_TRACKING_CONSENT')) {
        Configuration::updateValue('COMFINO_PAYWALL_TRACKING_CONSENT', false);
    }

    /* Initialize the remote flag name list (filled by the ComfinoPay push or the next token claim; also seeded by the
       first flag attributes fetch at checkout). */
    if (!Configuration::hasKey('COMFINO_REMOTE_FLAGS')) {
        Configuration::updateValue('COMFINO_REMOTE_FLAGS', '');
    }

    /* Drop stale opcache entries for the module tree, so an upgrade cannot leave old src/ files running under the new
       comfino.php. */
    if (function_exists('opcache_invalidate')) {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(_PS_MODULE_DIR_ . $module->name, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    @opcache_invalidate($file->getPathname(), true);
                }
            }
        } catch (Throwable $e) {
            // Best effort only.
        }
    }

    Main::updateUpgradeLog('Upgrade script for 4.4.0 executed.');

    // Report the shop environment to ComfinoPay on upgrade (fire-and-forget).
    if (!empty(ConfigManager::getApiKey())) {
        ShopEnvironmentReporter::report();
    }

    return true;
}
