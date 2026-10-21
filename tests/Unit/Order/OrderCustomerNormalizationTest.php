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

namespace Comfino\Tests\Unit\Order;

use Comfino\Order\OrderManager;
use PHPUnit\Framework\TestCase;

/**
 * Customer data normalization before the order is created.
 */
class OrderCustomerNormalizationTest extends TestCase
{
    /**
     * @dataProvider phoneProvider
     */
    public function testPhone($input, $expected)
    {
        $this->assertSame($expected, OrderManager::normalizePhoneNumber($input));
    }

    public function phoneProvider()
    {
        return [
            ['123 456 789', '123456789'],
            ['(12) 345-67-89', '123456789'],
            ['+48 123.456.789', '+48123456789'],
            ['0048 123 456 789', '+48123456789'],
            ['  ', ''],
        ];
    }

    public function testName()
    {
        $this->assertSame('Anna Maria', OrderManager::normalizeName("  Anna \t Maria  "));
        $this->assertSame('', OrderManager::normalizeName('   '));
    }
}
