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
 * @param Comfino $module
 *
 * @return bool
 */
function upgrade_module_3_0_0($module)
{
    if (file_exists(_PS_MODULE_DIR_ . 'comfino/override')) {
        // Remove admin controller override from older versions.
        unlink(_PS_MODULE_DIR_ . 'comfino/override/index.php');
        unlink(_PS_MODULE_DIR_ . 'comfino/override/controllers/index.php');
        unlink(_PS_MODULE_DIR_ . 'comfino/override/controllers/admin/index.php');
        unlink(_PS_MODULE_DIR_ . 'comfino/override/controllers/admin/AdminOrdersController.php');

        rmdir(_PS_MODULE_DIR_ . 'comfino/override/controllers/admin');
        rmdir(_PS_MODULE_DIR_ . 'comfino/override/controllers');
        rmdir(_PS_MODULE_DIR_ . 'comfino/override');

        $comfinoOverriddenControllerPath = _PS_OVERRIDE_DIR_ . 'controllers/admin/AdminOrdersController.php';

        if (file_exists($comfinoOverriddenControllerPath) && strpos(file_get_contents($comfinoOverriddenControllerPath), 'comfino') !== false) {
            unlink($comfinoOverriddenControllerPath);
            unlink(_PS_CACHE_DIR_ . 'class_index.php');
        }
    }

    return true;
}
