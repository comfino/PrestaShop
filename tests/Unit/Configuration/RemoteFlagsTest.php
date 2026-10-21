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
use Comfino\Api\Response\GetUserSettings;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Configuration\ConfigManager;
use Comfino\Main;
use Comfino\PluginShared\CacheManager;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * Remote flags pipeline: flag name list, cached flag attributes (tag "remote_flags"), sanitizer, checkout projection.
 */
class RemoteFlagsTest extends TestCase
{
    const PROMO = ['amount' => 10000, 'minAmount' => 100000, 'maxAmount' => 500000, 'allowedTypes' => ['PAY_LATER']];

    /** @var int */
    public static $apiCalls = 0;
    /** @var array|\Throwable */
    public static $apiResponse = [];
    /** @var string[] */
    public static $apiHeaders = [];

    protected function setUp()
    {
        parent::setUp();

        TestState::reset();
        CacheManager::init('');
        self::$apiCalls = 0;
        self::$apiResponse = [];
        self::$apiHeaders = [];

        \Configuration::updateValue('COMFINO_API_KEY', 'TEST_API_KEY_0123456789');
        $this->setApiClient(new StubUserSettingsClient());
    }

    protected function tearDown()
    {
        $this->setApiClient((new ApiClientFactory())->createClient('TEST_API_KEY', 'test'));
        CacheManager::init('');
        TestState::reset();

        parent::tearDown();
    }

    public function testCacheMissFetchesSanitizesAndCaches()
    {
        self::$apiResponse = ['PROMO' => self::PROMO, 'bad-name' => ['a' => 1]];

        $attributes = ConfigManager::getRemoteFlagAttributes();

        $this->assertSame(['PROMO' => self::PROMO], $attributes);
        $this->assertSame(1, self::$apiCalls);

        ConfigManager::getRemoteFlagAttributes();

        $this->assertSame(1, self::$apiCalls, 'Cache hit must not call the API.');
    }

    public function testInvalidationByTagRefetches()
    {
        self::$apiResponse = ['PROMO' => self::PROMO];
        ConfigManager::getRemoteFlagAttributes();

        self::$apiResponse = ['PROMO' => ['amount' => 20000]];
        CacheManager::getCachePool()->invalidateTags(['remote_flags']);

        $this->assertSame(['PROMO' => ['amount' => 20000]], ConfigManager::getRemoteFlagAttributes());
        $this->assertSame(2, self::$apiCalls);
    }

    public function testTtlIsCappedAndShortenedByHeader()
    {
        $this->assertSame(86400, $this->cachedTtlFor(null));
        $this->assertSame(86400, $this->cachedTtlFor('0'));
        $this->assertSame(86400, $this->cachedTtlFor('172800'));
        $this->assertEquals(3600, $this->cachedTtlFor('3600'), '', 5);
    }

    public function testFailureIsCachedAndDoesNotEscape()
    {
        self::$apiResponse = new \RuntimeException('down');

        $this->assertSame([], ConfigManager::getRemoteFlagAttributes());
        $this->assertSame([], ConfigManager::getRemoteFlagAttributes());
        $this->assertSame(1, self::$apiCalls, 'Failure must be cached, not retried on every call.');
        $this->assertEquals(300, $this->cachedTtl(), '', 5);
    }

    public function testNoApiKeyMakesNoCall()
    {
        \Configuration::updateValue('COMFINO_API_KEY', '');

        $this->assertSame([], ConfigManager::getRemoteFlagAttributes());
        $this->assertSame(0, self::$apiCalls);
        $this->assertNull(CacheManager::get('remote_flag_attributes'));
    }

    public function testMultistoreScopesAreIsolated()
    {
        self::$apiResponse = ['PROMO' => self::PROMO];

        CacheManager::init(sys_get_temp_dir() . '/comfino-tests/cache-' . uniqid(), '1');
        ConfigManager::getRemoteFlagAttributes();
        $this->assertNotNull(CacheManager::get('remote_flag_attributes'));

        CacheManager::init(CacheManager::getCacheFullPath() ? dirname(CacheManager::getCacheFullPath(), 2) : '', '2');
        $this->assertNull(CacheManager::get('remote_flag_attributes'));
    }

    public function testNameListUpdatedOnlyWhenChanged()
    {
        $this->assertTrue(ConfigManager::updateRemoteFlagsIfChanged('B_FLAG, A_FLAG,bad flag,A_FLAG'));
        $this->assertSame(['A_FLAG', 'B_FLAG'], ConfigManager::getRemoteFlags());
        $this->assertFalse(ConfigManager::updateRemoteFlagsIfChanged('A_FLAG,B_FLAG'));
    }

    public function testNameListFilteredOnRead()
    {
        \Configuration::updateValue('COMFINO_REMOTE_FLAGS', 'GOOD_FLAG,<script>,bad-name');
        TestState::resetConfigManagers();

        $this->assertSame(['GOOD_FLAG'], ConfigManager::getRemoteFlags());
    }

    public function testRemoteFlagsIsAccessibleAndAllowlistKept()
    {
        $this->assertContains('COMFINO_REMOTE_FLAGS', ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
        $this->assertCount(38, ConfigManager::ACCESSIBLE_CONFIG_OPTIONS, '37 options of 4.3.1 plus COMFINO_REMOTE_FLAGS.');
    }

    public function testEmptyNameListIsSeededFromBodyWithoutPromo()
    {
        self::$apiResponse = ['PROMO' => self::PROMO, 'PAYMENT_ITEM_PROMOTION' => [], 'OTHER_FLAG' => []];

        ConfigManager::getRemoteFlagAttributes();

        $this->assertSame(['PAYMENT_ITEM_PROMOTION', 'OTHER_FLAG'], ConfigManager::getRemoteFlags());
    }

    public function testSanitizerKeepsValuesUnchanged()
    {
        $odd = ['amount' => '5', 'minAmount' => -1, 'allowedTypes' => ['PAY_LATER', '<script>'], 'renamed' => ['y' => null]];
        $sanitized = ConfigManager::sanitizeRemoteFlagAttributes([
            'PROMO' => $odd,
            'EMPTY_HOLDER' => [],
            'SCALAR_FLAG' => 'text',
            str_repeat('A', 65) => [],
            'mixedCase_1' => ['x' => 1],
            'NESTED_CASE' => ['camelCase' => 1, 'snake_case' => ['Mixed_Key' => true]],
            'bad-name' => ['x' => 1],
            'BROKEN_UTF8' => ['x' => "\xB1\x31"],
            'BAD_FLOAT' => [NAN],
            0 => ['x' => 1],
        ]);

        $this->assertSame(
            ['PROMO' => $odd, 'EMPTY_HOLDER' => [], 'SCALAR_FLAG' => 'text', 'NESTED_CASE' => ['camelCase' => 1, 'snake_case' => ['Mixed_Key' => true]]],
            $sanitized
        );
        $this->assertSame([], ConfigManager::sanitizeRemoteFlagAttributes('not-an-array'));
    }

    public function testCheckoutProjectionForwardsBodyVerbatim()
    {
        self::$apiResponse = [
            'PROMO' => ['someNewField' => ['nested' => true]],
            'PAYMENT_ITEM_PROMOTION' => ['text' => ['pl' => 'Promocja']],
        ];
        \Configuration::updateValue('COMFINO_REMOTE_FLAGS', 'PAYMENT_ITEM_PROMOTION');

        $this->assertSame(self::$apiResponse, ConfigManager::getCheckoutFlagAttributes());
    }

    public function testCheckoutConfigEncodingIsScriptSafe()
    {
        $json = Main::encodeCheckoutConfig([
            'flagAttributes' => ['X' => ['text' => '</script><b>"\'&']],
            'flags' => null,
        ]);

        $this->assertNotContains('</script>', $json);
        $this->assertNotContains('<', $json);
        $this->assertSame(
            ['flagAttributes' => ['X' => ['text' => '</script><b>"\'&']], 'flags' => null],
            json_decode($json, true)
        );
    }

    /**
     * @param string|null $ttlHeader
     */
    private function cachedTtlFor($ttlHeader): int
    {
        TestState::reset();
        CacheManager::init('');
        \Configuration::updateValue('COMFINO_API_KEY', 'TEST_API_KEY_0123456789');
        self::$apiResponse = ['PROMO' => self::PROMO];
        self::$apiHeaders = $ttlHeader === null ? [] : ['Comfino-Cache-TTL' => $ttlHeader];

        ConfigManager::getRemoteFlagAttributes();

        return $this->cachedTtl();
    }

    private function cachedTtl(): int
    {
        $item = CacheManager::getCachePool()->getItem('remote_flag_attributes');
        $item->isHit(); // Items are loaded lazily; the expiry is known after the first read.

        return (int) $item->getExpirationTimestamp() - time();
    }

    private function setApiClient($client)
    {
        $property = new \ReflectionProperty(ApiClient::class, 'apiClient');
        $property->setAccessible(true);
        $property->setValue(null, $client);
    }
}

class StubUserSettingsClient extends \Comfino\Common\Api\Client
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

    public function getUserSettings(): \Comfino\Api\Response\GetUserSettings
    {
        RemoteFlagsTest::$apiCalls++;

        if (RemoteFlagsTest::$apiResponse instanceof \Throwable) {
            throw RemoteFlagsTest::$apiResponse;
        }

        $reflection = new \ReflectionClass(GetUserSettings::class);
        $response = $reflection->newInstanceWithoutConstructor();
        $response->flags = RemoteFlagsTest::$apiResponse;

        $headers = new \ReflectionProperty(\Comfino\Api\Response::class, 'headers');
        $headers->setAccessible(true);
        $headers->setValue($response, RemoteFlagsTest::$apiHeaders);

        return $response;
    }
}
