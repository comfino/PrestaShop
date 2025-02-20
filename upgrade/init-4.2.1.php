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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @return bool
 */
function upgrade_module_4_2_1(Comfino $module)
{
    if (!$module->checkEnvironment()) {
        return false;
    }

    ConfigManager::updateWidgetCode(WIDGET_INIT_SCRIPT_LAST_HASH);

    // Initialize new configuration options and set widget type as extended-modal for all upgraded shops, update API connection timeouts.
    ConfigManager::updateConfiguration([
        'COMFINO_WIDGET_OFFER_TYPES' => [Configuration::get('COMFINO_WIDGET_OFFER_TYPE')],
        'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => ['INSTALLMENTS_ZERO_PERCENT', 'PAY_LATER', 'LEASING'],
        'COMFINO_WIDGET_TYPE' => 'extended-modal',
        'COMFINO_API_CONNECT_TIMEOUT' => 3,
        'COMFINO_API_TIMEOUT' => 5,
    ]);

    return true;
}
