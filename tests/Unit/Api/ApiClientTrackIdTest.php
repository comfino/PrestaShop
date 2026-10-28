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

use Comfino\Api\ApiClient;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * Checkout trackId pinning: the consent-gated journey cookie wins over the checkout cookie, otherwise Layer 0 applies.
 */
class ApiClientTrackIdTest extends TestCase
{
    const JOURNEY_TRACK_ID = 'journey-track-id.1';
    const CHECKOUT_TRACK_ID = 'checkout-track-id.1';

    protected function setUp()
    {
        parent::setUp();

        TestState::reset();
        $this->resetApiClient();
        $_COOKIE = [];
    }

    protected function tearDown()
    {
        $_COOKIE = [];
        $this->resetApiClient();
        TestState::reset();

        parent::tearDown();
    }

    public function testJourneyCookieWinsWhenTrackingConsentEnabled()
    {
        \Configuration::updateValue('COMFINO_PAYWALL_TRACKING_CONSENT', '1');
        $_COOKIE['comfino_journey_track_id'] = self::JOURNEY_TRACK_ID;
        $_COOKIE['comfino_checkout_track_id'] = self::CHECKOUT_TRACK_ID;

        ApiClient::pinCheckoutTrackId();

        $this->assertSame(self::JOURNEY_TRACK_ID, ApiClient::getInstance()->getTrackId());
    }

    public function testJourneyCookieIgnoredWhenTrackingConsentDisabled()
    {
        \Configuration::updateValue('COMFINO_PAYWALL_TRACKING_CONSENT', '0');
        $_COOKIE['comfino_journey_track_id'] = self::JOURNEY_TRACK_ID;
        $_COOKIE['comfino_checkout_track_id'] = self::CHECKOUT_TRACK_ID;

        ApiClient::pinCheckoutTrackId();

        $this->assertSame(self::CHECKOUT_TRACK_ID, ApiClient::getInstance()->getTrackId());
    }

    public function testInvalidJourneyCookieFallsBackToCheckoutCookie()
    {
        \Configuration::updateValue('COMFINO_PAYWALL_TRACKING_CONSENT', '1');
        $_COOKIE['comfino_journey_track_id'] = "bad\r\nvalue";
        $_COOKIE['comfino_checkout_track_id'] = self::CHECKOUT_TRACK_ID;

        ApiClient::pinCheckoutTrackId();

        $this->assertSame(self::CHECKOUT_TRACK_ID, ApiClient::getInstance()->getTrackId());
    }

    public function testFreshTrackIdWithoutValidCookies()
    {
        \Configuration::updateValue('COMFINO_PAYWALL_TRACKING_CONSENT', '1');
        $_COOKIE['comfino_journey_track_id'] = str_repeat('a', 129);

        ApiClient::pinCheckoutTrackId();

        $trackId = ApiClient::getInstance()->getTrackId();

        $this->assertNotSame(str_repeat('a', 129), $trackId);
        $this->assertRegExp('/^[A-Za-z0-9_.:-]{1,128}$/', $trackId);
    }

    /**
     * Swaps the module's singleton for a fresh shared-library client, so no trackId leaks between tests.
     */
    private function resetApiClient()
    {
        $property = new \ReflectionProperty(ApiClient::class, 'apiClient');
        $property->setAccessible(true);
        $property->setValue(null, (new ApiClientFactory())->createClient('TEST_API_KEY', 'test'));
    }
}
