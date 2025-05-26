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
use Comfino\PluginShared\CacheManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return bool
 */
function upgrade_module_4_2_3(Comfino $module)
{
    if (!$module->checkEnvironment()) {
        return false;
    }

    if (ConfigManager::updateWidgetCode(WIDGET_INIT_SCRIPT_LAST_HASH)) {
        ConfigManager::updateConfigurationValue('COMFINO_WIDGET_TYPE', 'standard');
        ConfigManager::updateConfigurationValue('COMFINO_WIDGET_SHOW_PROVIDER_LOGOS', '0');
        ConfigManager::updateConfigurationValue('COMFINO_NEW_WIDGET_ACTIVE', '1');
        CacheManager::getCachePool()->clear();
    } else {
        ConfigManager::updateConfigurationValue('COMFINO_NEW_WIDGET_ACTIVE', '0');
    }

    return true;
}
