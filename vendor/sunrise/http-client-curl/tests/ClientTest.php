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

declare(strict_types=1);

namespace Sunrise\Http\Client\Curl\Tests;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Sunrise\Http\Client\Curl\Exception\ClientException;
use Sunrise\Http\Client\Curl\Exception\NetworkException;
use Sunrise\Http\Client\Curl\Exception\RequestException;
use Sunrise\Http\Client\Curl\Client;
use Sunrise\Http\Factory\RequestFactory;
use Sunrise\Http\Factory\ResponseFactory;

class ClientTest extends TestCase
{
    public function testConstructor()
    {
        $client = new Client(new ResponseFactory());
        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    public function testSendRequest()
    {
        $client = new Client(new ResponseFactory());
        $request = (new RequestFactory)->createRequest('GET', 'https://www.php.net/');
        $response = $client->sendRequest($request);
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('X-Request-Time'));
    }

    public function testSendRequests()
    {
        $client = new Client(new ResponseFactory());
        $requests = [];
        $requests[] = (new RequestFactory)->createRequest('GET', 'https://www.php.net/');
        $requests[] = (new RequestFactory)->createRequest('GET', 'https://www.php.net/');
        $responses = $client->sendRequests(...$requests);

        $this->assertInstanceOf(ResponseInterface::class, $responses[0]);
        $this->assertSame(200, $responses[0]->getStatusCode());
        $this->assertTrue($responses[0]->hasHeader('X-Request-Time'));

        $this->assertInstanceOf(ResponseInterface::class, $responses[1]);
        $this->assertSame(200, $responses[1]->getStatusCode());
        $this->assertTrue($responses[1]->hasHeader('X-Request-Time'));
    }

    public function testSendRequestWithEmptyUri()
    {
        $client = new Client(new ResponseFactory());
        $request = (new RequestFactory)->createRequest('GET', '');

        $this->expectException(NetworkExceptionInterface::class);
        // $this->expectExceptionMessage('<url> malformed');
        $client->sendRequest($request);
    }

    public function testClientException()
    {
        $previous = new RuntimeException();

        $exception = new ClientException('foo', 42, $previous);
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertInstanceOf(ClientExceptionInterface::class, $exception);

        $this->assertSame('foo', $exception->getMessage());
        $this->assertSame(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testNetworkException()
    {
        $request = (new RequestFactory)->createRequest('GET', 'http://php.net/');
        $previous = new RuntimeException();

        $exception = new NetworkException($request, 'foo', 42, $previous);
        $this->assertInstanceOf(ClientException::class, $exception);
        $this->assertInstanceOf(NetworkExceptionInterface::class, $exception);

        $this->assertSame($request, $exception->getRequest());
        $this->assertSame('foo', $exception->getMessage());
        $this->assertSame(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testRequestException()
    {
        $request = (new RequestFactory)->createRequest('GET', 'http://php.net/');
        $previous = new RuntimeException();

        $exception = new RequestException($request, 'foo', 42, $previous);
        $this->assertInstanceOf(ClientException::class, $exception);
        $this->assertInstanceOf(RequestExceptionInterface::class, $exception);

        $this->assertSame($request, $exception->getRequest());
        $this->assertSame('foo', $exception->getMessage());
        $this->assertSame(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
