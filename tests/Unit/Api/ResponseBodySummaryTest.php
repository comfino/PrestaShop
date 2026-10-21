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
use PHPUnit\Framework\TestCase;

/**
 * Error response bodies are reduced to readable text before they reach the logs and the error reports.
 */
class ResponseBodySummaryTest extends TestCase
{
    public function testHtmlErrorPageLosesStylesScriptsAndMarkup()
    {
        $html = '<!DOCTYPE html><html><head><title>x</title><style>.a { color: red; }</style></head>'
            . '<body><script>var a = 1;</script><h1>Unknown column &#039;comfino_discount&#039;</h1>'
            . "<p>in\n   INSERT</p></body></html>";

        $summary = ApiClient::summarizeResponseBody($html);

        $this->assertSame("[HTML response] Unknown column 'comfino_discount' in INSERT", $summary);
    }

    public function testJsonAndPlainTextAreKept()
    {
        $this->assertSame('{"error":"Bad request"}', ApiClient::summarizeResponseBody('{"error":"Bad request"}'));
        $this->assertSame('Internal Server Error', ApiClient::summarizeResponseBody('Internal Server Error'));
        $this->assertNull(ApiClient::summarizeResponseBody(null));
        $this->assertSame('', ApiClient::summarizeResponseBody(''));
    }

    public function testLongBodiesAreTruncated()
    {
        $summary = ApiClient::summarizeResponseBody(str_repeat('ż', 5000));

        $this->assertSame(2000 + strlen('... [truncated]'), mb_strlen($summary, 'UTF-8'));
        $this->assertStringEndsWith('... [truncated]', $summary);
    }
}
