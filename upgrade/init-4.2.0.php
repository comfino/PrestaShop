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

use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return bool
 */
function upgrade_module_4_2_0(Comfino $module)
{
    if (!$module->checkEnvironment()) {
        return false;
    }

    ConfigManager::updateWidgetCode();

    // Initialize new configuration options.
    ConfigManager::updateConfiguration(
        [
            'COMFINO_JS_PROD_PATH' => '',
            'COMFINO_CSS_PROD_PATH' => 'css',
            'COMFINO_JS_DEV_PATH' => '',
            'COMFINO_CSS_DEV_PATH' => 'css',
        ],
        false
    );

    // Update COMFINO_IGNORED_STATUSES option.
    if (is_array($ignoredStatuses = ConfigManager::getConfigurationValue('COMFINO_IGNORED_STATUSES'))
        && in_array(StatusManager::STATUS_CANCELLED_BY_SHOP, $ignoredStatuses, true)
    ) {
        ConfigManager::updateConfigurationValue('COMFINO_IGNORED_STATUSES', StatusManager::DEFAULT_IGNORED_STATUSES);
    }

    return true;
}
