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

use Comfino\Api\ApiClient;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Configuration\ConfigManager;
use Comfino\Extended\Api\Response\ClaimErrorLoggingToken as ClaimErrorLoggingTokenResponse;
use Comfino\PluginShared\CacheManager;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * The error-logging access token claim must not run on every request: not after a failed claim (the shared API client
 * reports one as a null response) and not after a token that is already due for a refresh again.
 */
class ErrorLoggingTokenRefreshTest extends TestCase
{
    const COOLDOWN_KEY = 'error_logging_token_claim_cooldown';

    /** @var int */
    public static $claims = 0;
    /** @var ClaimErrorLoggingTokenResponse|\Throwable|null */
    public static $claimResult;

    protected function setUp()
    {
        parent::setUp();

        TestState::reset();
        CacheManager::init('');
        self::$claims = 0;
        self::$claimResult = null;

        \Configuration::updateValue('COMFINO_API_KEY', 'TEST_API_KEY_0123456789');
        $this->setApiClient(new StubClaimTokenClient());
    }

    protected function tearDown()
    {
        $this->setApiClient((new ApiClientFactory())->createClient('TEST_API_KEY', 'test'));
        CacheManager::init('');
        TestState::reset();

        parent::tearDown();
    }

    public function testNoApiKeyMakesNoClaim()
    {
        \Configuration::updateValue('COMFINO_API_KEY', '');

        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(0, self::$claims);
    }

    public function testFailedClaimReportedAsNullStartsACooldown()
    {
        self::$claimResult = null;

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, self::$claims, 'Only the first request may call the API during the cooldown.');
        $this->assertSame('', ConfigManager::getErrorLoggingAccessToken());
        $this->assertNotNull(CacheManager::get(self::COOLDOWN_KEY));
    }

    public function testThrownClaimErrorStartsACooldown()
    {
        self::$claimResult = new \RuntimeException('Connection refused.');

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, self::$claims);
    }

    public function testUnparsableExpiryIsAFailureAndStoresNothing()
    {
        self::$claimResult = $this->tokenResponse('new-token', 'not a date');

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, self::$claims);
        $this->assertSame('', ConfigManager::getErrorLoggingAccessToken());
    }

    public function testHealthyTokenIsStoredWithoutACooldownAndNotClaimedAgain()
    {
        self::$claimResult = $this->tokenResponse('long-token', gmdate('c', time() + 86400));

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, self::$claims, 'A valid token is not refreshed.');
        $this->assertSame('long-token', ConfigManager::getErrorLoggingAccessToken());
        $this->assertNull(CacheManager::get(self::COOLDOWN_KEY));
    }

    public function testShortLivedTokenStartsACooldownInsteadOfBeingClaimedOnEveryRequest()
    {
        self::$claimResult = $this->tokenResponse('short-token', gmdate('c', time() + 1800));

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, self::$claims);
        $this->assertSame('short-token', ConfigManager::getErrorLoggingAccessToken());
        $this->assertNotNull(CacheManager::get(self::COOLDOWN_KEY));
    }

    /**
     * @param string $accessToken
     * @param string $expiresAt
     *
     * @return ClaimErrorLoggingTokenResponse
     */
    private function tokenResponse($accessToken, $expiresAt)
    {
        $response = (new \ReflectionClass(ClaimErrorLoggingTokenResponse::class))->newInstanceWithoutConstructor();
        $response->accessToken = $accessToken;
        $response->expiresAt = $expiresAt;

        $headers = new \ReflectionProperty(\Comfino\Api\Response::class, 'headers');
        $headers->setAccessible(true);
        $headers->setValue($response, []);

        return $response;
    }

    private function setApiClient($client)
    {
        $property = new \ReflectionProperty(ApiClient::class, 'apiClient');
        $property->setAccessible(true);
        $property->setValue(null, $client);
    }
}

class StubClaimTokenClient extends \Comfino\Common\Api\Client
{
    public function __construct()
    {
    }

    public function setCustomApiHost($host): void
    {
    }

    public function setApiKey($apiKey): void
    {
    }

    public function setApiLanguage($language): void
    {
    }

    public function enableSandboxMode(): void
    {
    }

    public function disableSandboxMode(): void
    {
    }

    public function claimErrorLoggingToken(): ?ClaimErrorLoggingTokenResponse
    {
        ErrorLoggingTokenRefreshTest::$claims++;

        if (ErrorLoggingTokenRefreshTest::$claimResult instanceof \Throwable) {
            throw ErrorLoggingTokenRefreshTest::$claimResult;
        }

        return ErrorLoggingTokenRefreshTest::$claimResult;
    }
}
