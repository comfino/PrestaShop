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

use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\RemoteValueValidator;
use Comfino\Tests\TestState;
use PHPUnit\Framework\TestCase;

/**
 * The remote write path rejects the same CSS URLs the admin form rejects, for all three options.
 */
class CssUrlValidationTest extends TestCase
{
    const OPTIONS = [
        'COMFINO_PAYWALL_CUSTOM_CSS_URL',
        'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
        'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
    ];

    protected function setUp()
    {
        parent::setUp();

        TestState::reset();
    }

    protected function tearDown()
    {
        TestState::reset();

        parent::tearDown();
    }

    /**
     * @dataProvider urlProvider
     */
    public function testValidator($url, $valid)
    {
        $this->assertSame($valid, RemoteValueValidator::isValidCssUrl($url));
    }

    public function urlProvider()
    {
        return [
            ['', true],
            ['https://cdn.example.com/a.css', true],
            ['http://cdn.example.com/a.css', false],
            ['javascript:alert(1)', false],
            ['data:text/css,body{}', false],
            ['//cdn.example.com/a.css', false],
            ['https://', false],
            ['https://evil.com/a.css"onload="x', false],
        ];
    }

    public function testRejectedOnWriteForAllOptions()
    {
        foreach (self::OPTIONS as $option) {
            \Configuration::updateValue($option, 'https://good.example.com/a.css');

            ConfigManager::save([$option => 'javascript:alert(1)']);

            $this->assertSame('https://good.example.com/a.css', \Configuration::get($option), $option);

            ConfigManager::save([$option => 'http://bad.example.com/a.css']);

            $this->assertSame('https://good.example.com/a.css', \Configuration::get($option), $option);

            ConfigManager::save([$option => 'https://new.example.com/b.css']);

            $this->assertSame('https://new.example.com/b.css', \Configuration::get($option), $option);
        }
    }
}
