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

namespace Sunrise\Http\ServerRequest\Tests;

/**
 * Import classes
 */
use Psr\Http\Message\ServerRequestInterface;
use Sunrise\Http\Message\Request;
use Sunrise\Http\ServerRequest\ServerRequest;
use Sunrise\Http\ServerRequest\UploadedFile;

/**
 * ServerRequestTest
 */
class ServerRequestTest extends AbstractTestCase
{

    /**
     * @return void
     */
    public function testContracts() : void
    {
        $request = new ServerRequest();

        $this->assertInstanceOf(Request::class, $request);
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
    }

    /**
     * @return void
     */
    public function testConstructor() : void
    {
        $stream = $this->createStream();
        $uploadedFile = new UploadedFile($stream);

        $dataset = [
            // method
            'POST',
            // URI
            'http://localhost:8000/foo?bar',
            // headers
            ['X-Foo' => 'bar'],
            // body
            $stream,
            // request target
            '/bar?baz',
            // protocol version
            '2.0',
            // server params
            ['foo' => 'bar'],
            // query params
            ['bar' => 'baz'],
            // cookie params
            ['baz' => 'bat'],
            // uploaded files
            ['bat' => $uploadedFile],
            // parsed body
            ['qux' => 'quux'],
            // attributes
            ['quux' => 'quuux'],
        ];

        $request = new ServerRequest(...$dataset);

        $this->assertSame($dataset[0], $request->getMethod());
        $this->assertSame($dataset[1], (string) $request->getUri());
        $this->assertSame($dataset[2]['X-Foo'], $request->getHeaderLine('X-Foo'));
        $this->assertSame($dataset[3], $request->getBody());
        $this->assertSame($dataset[4], $request->getRequestTarget());
        $this->assertSame($dataset[5], $request->getProtocolVersion());
        $this->assertSame($dataset[6], $request->getServerParams());
        $this->assertSame($dataset[7], $request->getQueryParams());
        $this->assertSame($dataset[8], $request->getCookieParams());
        $this->assertSame($dataset[9], $request->getUploadedFiles());
        $this->assertSame($dataset[10], $request->getParsedBody());
        $this->assertSame($dataset[11], $request->getAttributes());
    }

    /**
     * @return void
     */
    public function testConstructorWithInvalidUploadedFiles() : void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid uploaded files');

        new ServerRequest(null, null, null, null, null, null, [], [], [], ['foo' => 'bar']);
    }

    /**
     * @return void
     */
    public function testConstructorWithInvalidParsedBody() : void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parsed body');

        new ServerRequest(null, null, null, null, null, null, [], [], [], [], \STDOUT);
    }

    /**
     * @return void
     */
    public function testSetQueryParams() : void
    {
        $queryParams = ['foo' => 'bar'];

        $request = new ServerRequest();
        $this->assertSame([], $request->getQueryParams());

        $clone = $request->withQueryParams($queryParams);
        $this->assertInstanceOf(ServerRequestInterface::class, $clone);
        $this->assertNotSame($request, $clone);
        $this->assertSame([], $request->getQueryParams());
        $this->assertSame($queryParams, $clone->getQueryParams());
    }

    /**
     * @return void
     */
    public function testSetCookieParams() : void
    {
        $cookieParams = ['foo' => 'bar'];

        $request = new ServerRequest();
        $this->assertSame([], $request->getCookieParams());

        $clone = $request->withCookieParams($cookieParams);
        $this->assertInstanceOf(ServerRequestInterface::class, $clone);
        $this->assertNotSame($request, $clone);
        $this->assertSame([], $request->getCookieParams());
        $this->assertSame($cookieParams, $clone->getCookieParams());
    }

    /**
     * @return void
     */
    public function testSetUploadedFiles() : void
    {
        $uploadedFiles = [];

        $stream = $this->createStream();
        $uploadedFile = new UploadedFile($stream);
        $uploadedFiles['foo'] = $uploadedFile;

        $request = new ServerRequest();
        $this->assertSame([], $request->getUploadedFiles());

        $clone = $request->withUploadedFiles($uploadedFiles);
        $this->assertInstanceOf(ServerRequestInterface::class, $clone);
        $this->assertNotSame($request, $clone);
        $this->assertSame([], $request->getUploadedFiles());
        $this->assertSame($uploadedFiles, $clone->getUploadedFiles());
    }

    /**
     * @return void
     */
    public function testSetInvalidUploadedFiles() : void
    {
        $request = new ServerRequest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid uploaded files');

        $request->withUploadedFiles(['foo' => 'bar']);
    }

    /**
     * @return void
     */
    public function testSetParsedBody() : void
    {
        $parsedBody = ['foo' => 'bar'];

        $request = new ServerRequest();
        $this->assertNull($request->getParsedBody());

        $clone = $request->withParsedBody($parsedBody);
        $this->assertInstanceOf(ServerRequestInterface::class, $clone);
        $this->assertNotSame($request, $clone);
        $this->assertNull($request->getParsedBody());
        $this->assertSame($parsedBody, $clone->getParsedBody());
    }

    /**
     * @return void
     */
    public function testSetInvalidParsedBody() : void
    {
        $request = new ServerRequest();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parsed body');

        $request->withParsedBody(\STDOUT);
    }

    /**
     * @return void
     */
    public function testSetAttributes() : void
    {
        $request = new ServerRequest();
        $this->assertSame([], $request->getAttributes());
        $this->assertNull($request->getAttribute('foo'));
        $this->assertFalse($request->getAttribute('foo', false));

        $clone = $request->withAttribute('foo', 'bar');
        $this->assertInstanceOf(ServerRequestInterface::class, $clone);
        $this->assertNotSame($request, $clone);
        $this->assertSame([], $request->getAttributes());
        $this->assertNull($request->getAttribute('foo'));
        $this->assertSame(['foo' => 'bar'], $clone->getAttributes());
        $this->assertSame('bar', $clone->getAttribute('foo'));
    }

    /**
     * @return void
     */
    public function testRemoveAttributes() : void
    {
        $request = (new ServerRequest)->withAttribute('foo', 'bar');

        $clone = $request->withoutAttribute('foo');
        $this->assertInstanceOf(ServerRequestInterface::class, $clone);
        $this->assertNotSame($request, $clone);
        $this->assertSame(['foo' => 'bar'], $request->getAttributes());
        $this->assertSame('bar', $request->getAttribute('foo'));
        $this->assertSame([], $clone->getAttributes());
        $this->assertNull($clone->getAttribute('foo'));
    }
}
