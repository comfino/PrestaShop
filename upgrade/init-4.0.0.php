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
use Comfino\ShopStatusManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_4_0_0(Comfino $module): bool
{
    Configuration::deleteByName('COMFINO_REGISTERED_AT');
    Configuration::deleteByName('COMFINO_SANDBOX_REGISTERED_AT');

    Configuration::updateValue('COMFINO_IGNORED_STATUSES', implode(',', StatusManager::DEFAULT_IGNORED_STATUSES));
    Configuration::updateValue('COMFINO_FORBIDDEN_STATUSES', implode(',', StatusManager::DEFAULT_FORBIDDEN_STATUSES));
    Configuration::updateValue('COMFINO_STATUS_MAP', json_encode(ShopStatusManager::DEFAULT_STATUS_MAP));

    $logFilePath = _PS_MODULE_DIR_ . $module->name . '/payment_log.log';

    if (file_exists($logFilePath)) {
        @rename($logFilePath, _PS_MODULE_DIR_ . $module->name . '/var/log/errors.log');
    }

    return true;
}
