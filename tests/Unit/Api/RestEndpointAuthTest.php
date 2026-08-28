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
use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpointManager;
use ComfinoExternal\Sunrise\Http\Factory\ServerRequestFactory;
use ComfinoExternal\Sunrise\Http\Factory\StreamFactory;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the REST endpoint signature check.
 *
 * A CR-Signature is calculated as sha3-256(apiKey . requestBody). When an API key slot is unconfigured its
 * value is null or an empty string, so the signature collapses to sha3-256(requestBody) - a value any caller
 * can compute without knowing a secret. These tests assert that such a signature is refused while a real
 * production key is configured.
 */
class RestEndpointAuthTest extends TestCase
{
    const PRODUCTION_API_KEY = 'PRODUCTION_KEY_1234567890';
    const SANDBOX_API_KEY = 'SANDBOX_KEY_0987654321';
    const REQUEST_BODY = '{"COMFINO_DEBUG":true}';

    protected function setUp()
    {
        parent::setUp();

        $this->resetEndpointManager();
    }

    protected function tearDown()
    {
        $this->resetEndpointManager();

        parent::tearDown();
    }

    /**
     * @dataProvider unusableApiKeysProvider
     *
     * @param mixed $unusableApiKey
     */
    public function testApiKeyFilterDropsUnusableKeys($unusableApiKey, $description)
    {
        $filteredKeys = ApiService::filterApiKeys([self::PRODUCTION_API_KEY, $unusableApiKey]);

        $this->assertSame([self::PRODUCTION_API_KEY], $filteredKeys, $description);
    }

    public function unusableApiKeysProvider()
    {
        return [
            'unconfigured key slot (null)' => [null, 'A null key must never be used as a signature secret.'],
            'unconfigured key slot (empty string)' => ['', 'An empty key must never be used as a signature secret.'],
            'whitespace only' => ['                ', 'A blank key must never be used as a signature secret.'],
            'too short to be a real key' => ['SHORT_KEY', 'A key shorter than 16 characters cannot be a Comfino key.'],
            'wrong type' => [12345678901234567890, 'A non-string key must never be used as a signature secret.'],
        ];
    }

    public function testApiKeyFilterKeepsUsableKeys()
    {
        $this->assertSame(
            [self::PRODUCTION_API_KEY, self::SANDBOX_API_KEY],
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY, self::SANDBOX_API_KEY])
        );
    }

    public function testSignatureCalculatedWithAnEmptyKeyIsRejected()
    {
        $manager = $this->createEndpointManager(
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY, null])
        );

        $this->assertFalse(
            $this->requestIsAuthorized($manager, hash('sha3-256', '' . self::REQUEST_BODY)),
            'A signature calculated with an empty API key must not authorize a request.'
        );
    }

    public function testSignatureCalculatedWithTheProductionKeyIsAccepted()
    {
        $manager = $this->createEndpointManager(
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY, null])
        );

        $this->assertTrue(
            $this->requestIsAuthorized($manager, hash('sha3-256', self::PRODUCTION_API_KEY . self::REQUEST_BODY)),
            'A correctly signed request must still be authorized.'
        );
    }

    public function testRequestIsRejectedWhenNoApiKeyIsConfigured()
    {
        $manager = $this->createEndpointManager(ApiService::filterApiKeys([null, '']));

        $this->assertFalse(
            $this->requestIsAuthorized($manager, hash('sha3-256', '' . self::REQUEST_BODY)),
            'A shop without any configured API key must not authorize any request.'
        );
    }

    /**
     * The test environment key is passed to the endpoint manager only while the shop runs in sandbox mode,
     * so a request signed with it must not be authorized on a production shop.
     */
    public function testSandboxKeyIsNotAcceptedOutsideSandboxMode()
    {
        $manager = $this->createEndpointManager(
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY])
        );

        $this->assertFalse(
            $this->requestIsAuthorized($manager, hash('sha3-256', self::SANDBOX_API_KEY . self::REQUEST_BODY)),
            'The test environment key must not authorize requests while sandbox mode is disabled.'
        );
    }

    public function testSandboxKeyIsAcceptedInSandboxMode()
    {
        $manager = $this->createEndpointManager(
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY, self::SANDBOX_API_KEY])
        );

        $this->assertTrue(
            $this->requestIsAuthorized($manager, hash('sha3-256', self::SANDBOX_API_KEY . self::REQUEST_BODY)),
            'The test environment key must authorize requests while sandbox mode is enabled.'
        );
    }

    /**
     * @param string[] $apiKeys
     *
     * @return RestEndpointManager
     */
    private function createEndpointManager(array $apiKeys)
    {
        return (new ApiServiceFactory())->createService('PrestaShop', _PS_VERSION_, COMFINO_VERSION, $apiKeys);
    }

    /**
     * @return bool
     */
    private function requestIsAuthorized(RestEndpointManager $manager, $crSignature)
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://shop.example/module/comfino/configuration')
            ->withHeader('CR-Signature', $crSignature)
            ->withBody((new StreamFactory())->createStream(self::REQUEST_BODY));

        $verifyRequest = new \ReflectionMethod(RestEndpointManager::class, 'verifyRequest');
        $verifyRequest->setAccessible(true);

        try {
            $verifyRequest->invoke($manager, $request);
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }

    /**
     * The endpoint manager is a singleton which ignores the arguments of every call after the first one,
     * so it has to be discarded between test cases.
     */
    private function resetEndpointManager()
    {
        $instance = new \ReflectionProperty(RestEndpointManager::class, 'instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }
}
