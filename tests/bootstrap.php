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

/*
 * Minimal test bootstrap.
 *
 * Module source files guard themselves with a _PS_VERSION_ check and exit when loaded outside PrestaShop,
 * so the constants a PrestaShop runtime would define are stubbed here. Only constants are defined - no
 * PrestaShop classes are stubbed, so a test may only exercise code which does not reach the shop framework.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '8.1.0');
}

if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

if (!defined('COMFINO_VERSION')) {
    define('COMFINO_VERSION', '4.3.1');
}

if (!defined('COMFINO_MODULE_NAME')) {
    define('COMFINO_MODULE_NAME', 'comfino');
}

if (!defined('COMFINO_BUILD_TS')) {
    define('COMFINO_BUILD_TS', 0);
}
