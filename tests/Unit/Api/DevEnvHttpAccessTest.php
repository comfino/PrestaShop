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
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * Plain-HTTP access to a local ComfinoPay API stays available in development mode only (env variable TRUE and the
 * dev env vars option active) and only for an http:// API host.
 */
class DevEnvHttpAccessTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        TestState::reset();
    }

    protected function tearDown()
    {
        putenv('COMFINO_DEV_ENV');
        putenv('COMFINO_DEV_API_HOST');
        TestState::reset();

        parent::tearDown();
    }

    public function testHttpAllowedInDevMode()
    {
        $this->configure('TRUE', true, 'http://comfino-api.local');

        $options = $this->options();

        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);
        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_REDIR_PROTOCOLS]);
    }

    public function testHttpsOnlyWithoutEnvironmentVariable()
    {
        $this->configure(false, true, 'http://comfino-api.local');

        $this->assertSame([], $this->options());
    }

    public function testHttpsOnlyWithoutPluginOption()
    {
        $this->configure('TRUE', false, 'http://comfino-api.local');

        $this->assertSame([], $this->options());
    }

    public function testHttpsOnlyForHttpsHost()
    {
        $this->configure('TRUE', true, 'https://comfino-api.local');

        $this->assertSame([], $this->options());
    }

    /**
     * @param string|false $envValue
     */
    private function configure($envValue, bool $option, string $apiHost)
    {
        putenv($envValue === false ? 'COMFINO_DEV_ENV' : "COMFINO_DEV_ENV=$envValue");
        putenv("COMFINO_DEV_API_HOST=$apiHost");
        \Configuration::updateValue('COMFINO_DEV_ENV_VARS', $option ? '1' : '0');
    }

    private function options(): array
    {
        $method = new \ReflectionMethod(ApiClient::class, 'getDevEnvCurlOptions');
        $method->setAccessible(true);

        return $method->invoke(null);
    }
}
