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

namespace Sunrise\Uri\Tests;

use PHPUnit\Framework\TestCase;
use Sunrise\Uri\UriParser;
use Sunrise\Uri\Exception\InvalidUriException;

class UriParserTest extends TestCase
{
    public const TEST_URI = 'scheme://user:pass@host:3000/path?query#fragment';

    public function testConstructor()
    {
        $uri = new UriParser('');

        $this->assertInstanceOf(UriParser::class, $uri);
    }

    public function testConstructorWithInvalidString()
    {
        $this->expectException(InvalidUriException::class);
        $this->expectExceptionMessage('URI must be a string');

        $uri = new UriParser(null);
    }

    public function testGetScheme()
    {
        $uri = new UriParser(self::TEST_URI);

        $this->assertSame('scheme', $uri->getScheme()->present());
    }

    public function testGetUser()
    {
        $uri = new UriParser(self::TEST_URI);

        $this->assertSame('user', $uri->getUser()->present());
    }

    public function testGetEmptyUser()
    {
        $uri = new UriParser('//:password@localhost');

        $this->assertSame('', $uri->getUser()->present());
    }

    public function testGetPass()
    {
        $uri = new UriParser(self::TEST_URI);

        $this->assertSame('pass', $uri->getPass()->present());
    }

    public function testGetEmptyPass()
    {
        $uri = new UriParser('//username:@localhost');

        $this->assertSame('', $uri->getPass()->present());
    }

    public function testGetHost()
    {
        $uri = new UriParser(self::TEST_URI);

        $this->assertSame('host', $uri->getHost()->present());
    }

    public function testGetPort()
    {
        $uri = new UriParser(self::TEST_URI);

        $this->assertSame(3000, $uri->getPort()->present());
    }

    public function testGetPath()
    {
        $uri = new UriParser(self::TEST_URI);

        $this->assertSame('/path', $uri->getPath()->present());
    }

    public function testGetQuery()
    {
        $uri = new UriParser(self::TEST_URI);

        $this->assertSame('query', $uri->getQuery()->present());
    }

    public function testGetFragment()
    {
        $uri = new UriParser(self::TEST_URI);

        $this->assertSame('fragment', $uri->getFragment()->present());
    }

    public function testGetUserInfo()
    {
        $uri = new UriParser(self::TEST_URI);

        $this->assertSame('user:pass', $uri->getUserInfo()->present());
    }
}
