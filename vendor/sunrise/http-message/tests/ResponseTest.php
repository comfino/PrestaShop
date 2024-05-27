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
use Psr\Http\Message\ResponseInterface;
use Sunrise\Http\Message\Message;
use Sunrise\Http\Message\Response;

/**
 * Import constants
 */
use const Sunrise\Http\Message\REASON_PHRASES;

/**
 * ResponseTest
 */
class ResponseTest extends TestCase
{

    /**
     * @return void
     */
    public function testConstructor() : void
    {
        $mess = new Response();

        $this->assertInstanceOf(Message::class, $mess);
        $this->assertInstanceOf(ResponseInterface::class, $mess);
    }

    /**
     * @return void
     */
    public function testStatus() : void
    {
        $mess = new Response();
        $copy = $mess->withStatus(204);

        $this->assertInstanceOf(Message::class, $copy);
        $this->assertInstanceOf(ResponseInterface::class, $copy);
        $this->assertNotEquals($mess, $copy);

        // default values
        $this->assertSame(200, $mess->getStatusCode());
        $this->assertSame(REASON_PHRASES[200], $mess->getReasonPhrase());

        // assigned values
        $this->assertSame(204, $copy->getStatusCode());
        $this->assertSame(REASON_PHRASES[204], $copy->getReasonPhrase());
    }

    /**
     * @dataProvider figStatusProvider
     *
     * @return void
     */
    public function testFigStatus($statusCode, $reasonPhrase) : void
    {
        $mess = (new Response)->withStatus($statusCode);

        $this->assertSame($statusCode, $mess->getStatusCode());
        $this->assertSame($reasonPhrase, $mess->getReasonPhrase());
    }

    /**
     * @dataProvider invalidStatusCodeProvider
     *
     * @return void
     */
    public function testInvalidStatusCode($statusCode) : void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Response)->withStatus($statusCode);
    }

    /**
     * @return void
     */
    public function testUnknownStatusCode() : void
    {
        $mess = (new Response)->withStatus(599);

        $this->assertSame('Unknown Status Code', $mess->getReasonPhrase());
    }

    /**
     * @dataProvider invalidReasonPhraseProvider
     *
     * @return void
     */
    public function testInvalidReasonPhrase($reasonPhrase) : void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Response)->withStatus(200, $reasonPhrase);
    }

    /**
     * @return void
     */
    public function testCustomReasonPhrase() : void
    {
        $mess = (new Response)->withStatus(200, 'test');

        $this->assertSame('test', $mess->getReasonPhrase());
    }

    // Providers...

    /**
     * @return array
     */
    public function figStatusProvider() : array
    {
        return [
            [200, REASON_PHRASES[200] ?? ''],
        ];
    }

    /**
     * @return array
     */
    public function invalidStatusCodeProvider() : array
    {
        return [
            [0],
            [99],
            [600],

            // other types
            [true],
            [false],
            ['100'],
            [100.0],
            [[]],
            [new \stdClass],
            [\STDOUT],
            [null],
            [function () {
            }],
        ];
    }

    /**
     * @return array
     */
    public function invalidReasonPhraseProvider() : array
    {
        return [
            ["bar\0baz"],
            ["bar\nbaz"],
            ["bar\rbaz"],

            // other types
            [true],
            [false],
            [1],
            [1.1],
            [[]],
            [new \stdClass],
            [\STDOUT],
            [null],
            [function () {
            }],
        ];
    }
}
