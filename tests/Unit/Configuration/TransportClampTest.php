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

namespace Comfino\Tests\Unit\Configuration;

use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\RemoteValueValidator;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * Stored transport values are clamped on read to bounds that survive the shared client's own normalization.
 */
class TransportClampTest extends TestCase
{
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

    /**
     * @dataProvider transportProvider
     */
    public function testClamp(array $stored, array $expected)
    {
        $this->assertSame($expected, RemoteValueValidator::clampTransport(...$stored));
    }

    public function transportProvider()
    {
        return [
            'zeros' => [[0, 0, 0], [1, 2, 1]],
            'negative' => [[-1, -1, -1], [1, 2, 1]],
            'huge' => [[99999, 99999, 99999], [10, 30, 3]],
            'connect above transfer' => [[8, 5, 2], [8, 9, 2]],
            'defaults' => [[3, 5, 3], [3, 5, 3]],
            'strings' => [['4', '9', '2'], [4, 9, 2]],
        ];
    }

    public function testClampAppliesToStoredConfiguration()
    {
        \Configuration::updateValue('COMFINO_API_CONNECT_TIMEOUT', 99999);
        \Configuration::updateValue('COMFINO_API_TIMEOUT', 0);
        \Configuration::updateValue('COMFINO_API_CONNECT_NUM_ATTEMPTS', 0);
        TestState::resetConfigManagers();

        $this->assertSame([10, 11, 1], ConfigManager::getTransportOptions());
    }
}
