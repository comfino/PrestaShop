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

use Comfino\Order\ShopStatusManager;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * Order states created before the ComfinoPay rebrand are reclaimed by their legacy "(Comfino)" names, not duplicated.
 */
class CustomOrderStatusReclaimTest extends TestCase
{
    const DETAILS = [
        'name' => 'Credit granted (ComfinoPay)',
        'name_pl' => 'Kredyt udzielony (ComfinoPay)',
    ];

    protected function setUp()
    {
        parent::setUp();

        TestState::reset();
    }

    protected function tearDown()
    {
        TestState::reset();

        parent::tearDown();
    }

    public function testLegacyNameDerivation()
    {
        $this->assertSame('Credit granted (Comfino)', ShopStatusManager::getLegacyStatusName(self::DETAILS['name']));
        $this->assertSame('Anulowano (Comfino)', ShopStatusManager::getLegacyStatusName('Anulowano (ComfinoPay)'));
    }

    public function testLegacyNamedStatusIsReclaimed()
    {
        $previous = [
            'en' => ['Credit granted (Comfino)' => ['id_order_state' => 42, 'id_lang' => 1, 'name' => 'x']],
            'pl' => ['Kredyt udzielony (Comfino)' => ['id_order_state' => 42, 'id_lang' => 2, 'name' => 'y']],
        ];

        $this->assertSame(42, $this->getStatusId('COMFINO_ACCEPTED', $previous));
        $this->assertSame(42, \Configuration::get('COMFINO_ACCEPTED'), 'The configuration key is restored.');
    }

    public function testNewNamedStatusIsReclaimed()
    {
        $previous = [
            'en' => ['Credit granted (ComfinoPay)' => ['id_order_state' => 7, 'id_lang' => 1, 'name' => 'x']],
            'pl' => ['Kredyt udzielony (ComfinoPay)' => ['id_order_state' => 7, 'id_lang' => 2, 'name' => 'y']],
        ];

        $this->assertSame(7, $this->getStatusId('COMFINO_ACCEPTED', $previous));
    }

    public function testMismatchedStatesAreNotReclaimed()
    {
        $previous = [
            'en' => ['Credit granted (Comfino)' => ['id_order_state' => 1, 'id_lang' => 1, 'name' => 'x']],
            'pl' => ['Kredyt udzielony (Comfino)' => ['id_order_state' => 2, 'id_lang' => 2, 'name' => 'y']],
        ];

        $this->assertSame(0, $this->getStatusId('COMFINO_ACCEPTED', $previous));
    }

    public function testConfiguredIdWins()
    {
        \Configuration::updateValue('COMFINO_ACCEPTED', '9');

        $this->assertSame(9, $this->getStatusId('COMFINO_ACCEPTED', []));
    }

    private function getStatusId(string $statusCode, array $previous): int
    {
        $method = new \ReflectionMethod(ShopStatusManager::class, 'getStatusId');
        $method->setAccessible(true);

        return $method->invoke(null, $statusCode, $previous, self::DETAILS);
    }
}
