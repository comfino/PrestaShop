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

namespace Comfino\Tests\Unit\Api;

use Comfino\Api\ApiService;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * Under Multistore, API keys are shop-scoped, so each shop's REST endpoint requests must be verified against that
 * shop's own key, not against the key of whichever shop context built the endpoint manager first.
 */
class EndpointManagerScopeTest extends TestCase
{
    const SHOP_1_API_KEY = 'SHOP_1_PRODUCTION_KEY_1234567890';
    const SHOP_2_API_KEY = 'SHOP_2_PRODUCTION_KEY_0987654321';
    const REQUEST_BODY = '{"status":"ACCEPTED"}';

    protected function setUp(): void
    {
        parent::setUp();

        TestState::reset();

        \Configuration::updateValue('COMFINO_API_KEY', self::SHOP_1_API_KEY, false, 1, 1);
        \Configuration::updateValue('COMFINO_API_KEY', self::SHOP_2_API_KEY, false, 1, 2);
    }

    protected function tearDown(): void
    {
        TestState::reset();

        parent::tearDown();
    }

    public function testEachShopSignsWithItsOwnKey(): void
    {
        \Shop::setShopContext(1);
        $this->assertSame(hash('sha3-256', self::SHOP_1_API_KEY . self::REQUEST_BODY), ApiService::getCrSignature(self::REQUEST_BODY));

        \Shop::setShopContext(2);
        $this->assertSame(hash('sha3-256', self::SHOP_2_API_KEY . self::REQUEST_BODY), ApiService::getCrSignature(self::REQUEST_BODY));

        // Switching back reuses shop 1's manager.
        \Shop::setShopContext(1);

        $this->assertSame(hash('sha3-256', self::SHOP_1_API_KEY . self::REQUEST_BODY), ApiService::getCrSignature(self::REQUEST_BODY));
    }

    public function testEveryScopeHasItsEndpointsRegistered(): void
    {
        \Shop::setShopContext(1);
        ApiService::init();

        // A scope first seen after init() still resolves its endpoints.
        \Shop::setShopContext(2);

        $this->assertSame('https://shop.test/module/comfino/transactionstatus', ApiService::getEndpointUrl('transactionStatus'));
    }

    public function testShopWithoutKeyIsNotVerifiedWithAnotherShopsKey(): void
    {
        \Shop::setShopContext(1);
        ApiService::getCrSignature(self::REQUEST_BODY);

        // Shop 3 has no key configured at all.
        \Shop::setShopContext(3);

        $this->expectException(AccessDenied::class);

        ApiService::getCrSignature(self::REQUEST_BODY);
    }
}
