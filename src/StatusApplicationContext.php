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

namespace Comfino;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Marks the time span in which the module itself applies an order state received from ComfinoPay.
 *
 * Order state changes made while processing a status notification fire `actionOrderStatusPostUpdate` like any
 * other change. The hook checks this flag, so a cancellation reported by ComfinoPay is never echoed back as a
 * cancellation request.
 */
class StatusApplicationContext
{
    /** @var int */
    private static $depth = 0;

    /**
     * @return void
     */
    public static function enter()
    {
        ++self::$depth;
    }

    /**
     * @return void
     */
    public static function leave()
    {
        if (self::$depth > 0) {
            --self::$depth;
        }
    }

    /**
     * @return bool
     */
    public static function isActive()
    {
        return self::$depth > 0;
    }
}
