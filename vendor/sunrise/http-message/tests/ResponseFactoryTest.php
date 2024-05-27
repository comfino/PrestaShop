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

namespace Sunrise\Http\Message\Tests;

/**
 * Import classes
 */
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Sunrise\Http\Message\ResponseFactory;

/**
 * ResponseFactoryTest
 */
class ResponseFactoryTest extends TestCase
{

    /**
     * @return void
     */
    public function testConstructor() : void
    {
        $factory = new ResponseFactory();

        $this->assertInstanceOf(ResponseFactoryInterface::class, $factory);
    }

    /**
     * @return void
     */
    public function testCreateResponse() : void
    {
        $statusCode = 204;
        $reasonPhrase = 'No Content';

        $response = (new ResponseFactory)
            ->createResponse($statusCode, $reasonPhrase);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame($statusCode, $response->getStatusCode());
        $this->assertSame($reasonPhrase, $response->getReasonPhrase());

        // default body of the response...
        $this->assertInstanceOf(StreamInterface::class, $response->getBody());
        $this->assertTrue($response->getBody()->isSeekable());
        $this->assertTrue($response->getBody()->isWritable());
        $this->assertTrue($response->getBody()->isReadable());
        $this->assertSame('php://temp', $response->getBody()->getMetadata('uri'));
    }

    /**
     * @return void
     */
    public function testCreateHtmlResponse() : void
    {
        $content = '<pre>foo bar</pre>';

        $response = (new ResponseFactory)
            ->createHtmlResponse(400, $content);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame($content, (string) $response->getBody());
    }

    /**
     * @return void
     */
    public function testCreateJsonResponse() : void
    {
        $payload = ['foo' => '<bar>'];
        $options = \JSON_HEX_TAG;

        $response = (new ResponseFactory)
            ->createJsonResponse(400, $payload, $options);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame(\json_encode($payload, $options), (string) $response->getBody());
    }

    /**
     * @return void
     */
    public function testCreateResponseWithInvalidJson() : void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum stack depth exceeded');

        $response = (new ResponseFactory)
            ->createJsonResponse(200, [[]], 0, 1);
    }
}
