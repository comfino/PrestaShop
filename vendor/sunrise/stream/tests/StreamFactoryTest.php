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

namespace Sunrise\Stream\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Sunrise\Stream\Exception\UnopenableStreamException;
use Sunrise\Stream\StreamFactory;

class StreamFactoryTest extends TestCase
{
    public function testConstructor()
    {
        $factory = new StreamFactory();

        $this->assertInstanceOf(StreamFactoryInterface::class, $factory);
    }

    public function testCreateStream()
    {
        $stream = (new StreamFactory)->createStream('065036e5-ea69-460a-9491-01f85289ab92');
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertTrue($stream->isReadable());
        $this->assertTrue($stream->isWritable());
        $this->assertEquals('php://temp', $stream->getMetadata('uri'));
        $this->assertEquals(0, $stream->tell());
        $this->assertEquals('065036e5-ea69-460a-9491-01f85289ab92', (string) $stream);
        $stream->close();
    }

    public function testCreateStreamFromFile()
    {
        $stream = (new StreamFactory)->createStreamFromFile('php://memory', 'r+b');
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $stream->close();
    }

    public function testCreateStreamFromResource()
    {
        $stream = (new StreamFactory)->createStreamFromResource(\fopen('php://memory', 'r+b'));
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $stream->close();
    }

    public function testCreateStreamFromUnopenableFile()
    {
        $this->expectException(UnopenableStreamException::class);
        $this->expectExceptionMessage('Unable to open file "/a1fd94f5-9390-41b8-a3e3-8039b6015db6" in mode "r"');

        (new StreamFactory)->createStreamFromFile('/a1fd94f5-9390-41b8-a3e3-8039b6015db6', 'r');
    }

    public function testCreateStreamWithTemporaryFile()
    {
        $stream = (new StreamFactory)->createStreamFromTemporaryFile('c4ab0f0b-3ca6-43df-a58b-51e7eec44090');
        $this->assertStringEqualsFile($stream->getMetadata('uri'), 'c4ab0f0b-3ca6-43df-a58b-51e7eec44090');
    }
}
