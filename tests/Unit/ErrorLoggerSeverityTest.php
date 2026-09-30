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

namespace Comfino\Tests\Unit;

use Comfino\Api\Exception\NonJsonResponse;
use Comfino\Api\Exception\NotFound;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Api\Exception\TooManyRequests;
use Comfino\Common\Exception\ConnectionTimeout;
use Comfino\Common\Backend\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\ErrorSeverity;
use PHPUnit\Framework\TestCase;

/**
 * Severity mapping of the vendored shared library, as used for error reports sent to the ComfinoPay API.
 *
 * Guards the plugin against a library rebuild that downgrades plugin and platform errors to warnings again.
 */
class ErrorLoggerSeverityTest extends TestCase
{
    /**
     * @dataProvider severityDataProvider
     */
    public function testResolveSeverity(\Throwable $exception, $expectedSeverity): void
    {
        $this->assertSame($expectedSeverity, ErrorLogger::resolveSeverity($exception));
    }

    public function severityDataProvider(): array
    {
        $idempotentNotFound = new NotFound('Not found', 404);
        $idempotentNotFound->setIdempotentFailure(true);

        return [
            'TypeError is an error' => [new \TypeError('type error'), ErrorSeverity::Error],
            'RuntimeException is an error' => [new \RuntimeException('runtime error'), ErrorSeverity::Error],
            'request validation error is not sent' => [new RequestValidationError('bad request', 400), null],
            'response validation error is not sent' => [new ResponseValidationError('invalid response'), null],
            'TooManyRequests is a warning' => [new TooManyRequests('rate limited', 429), ErrorSeverity::Warning],
            'HTML 5xx is a warning' => [
                new NonJsonResponse('bad gateway', 502, null, '', '', '<html></html>', 'text/html'),
                ErrorSeverity::Warning,
            ],
            'ServiceUnavailable is a warning' => [new ServiceUnavailable('down', 503), ErrorSeverity::Warning],
            'ConnectionTimeout is a warning' => [new ConnectionTimeout('timed out', 28), ErrorSeverity::Warning],
            'idempotent 404 is a warning' => [$idempotentNotFound, ErrorSeverity::Warning],
            'plain 404 is an error' => [new NotFound('Not found', 404), ErrorSeverity::Error],
        ];
    }
}
