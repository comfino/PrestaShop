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

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Configuration\ConfigManager;
use Comfino\Extended\Api\Dto\Plugin\ErrorSeverity;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ErrorLogger
{
    /** @var Common\Backend\ErrorLogger */
    private static $errorLogger;

    public static function init(): void
    {
        static $initialized = false;

        if (!$initialized) {
            self::getLoggerInstance()->init();

            $initialized = true;
        }
    }

    public static function getLoggerInstance(): Common\Backend\ErrorLogger
    {
        if (self::$errorLogger === null) {
            self::$errorLogger = Common\Backend\ErrorLogger::getInstance(
                ApiClient::getInstance(),
                _PS_MODULE_DIR_ . COMFINO_MODULE_NAME . '/var/log/errors.log',
                \Tools::getShopDomain(),
                'PrestaShop',
                'modules/' . COMFINO_MODULE_NAME,
                ConfigManager::getEnvironmentInfo()
            );
        }

        return self::$errorLogger;
    }

    public static function sendError(
        \Throwable $exception,
        string $context,
        string $errorCode,
        string $errorMessage,
        ?string $apiRequestUrl = null,
        ?string $apiRequest = null,
        ?string $apiResponse = null,
        ?string $stackTrace = null
    ): void {
        if ($exception instanceof AuthorizationError) {
            // Don't collect authorization errors caused by empty or wrong API key (response with status code 401).
            return;
        }

        $severity = Common\Backend\ErrorLogger::resolveSeverity($exception);

        if ($severity === null) {
            /* Validation outcome (rejected request or invalid response, already visible at API side) - keep a local
               record only, never report it. Payloads travel as context, so the log processor redacts them. */
            $logContext = array_filter(
                [
                    'error_code' => $errorCode,
                    'api_url' => $apiRequestUrl,
                    'api_request' => $apiRequest,
                    'api_response' => $apiResponse,
                ],
                static function ($value): bool {
                    return $value !== null;
                }
            );

            self::getLoggerInstance()->logError(
                '[' . Common\Backend\ErrorLogger::classifyException($exception)->value . "][$context]",
                $errorMessage,
                $logContext
            );

            return;
        }

        self::getLoggerInstance()->sendError(
            Common\Backend\ErrorLogger::classifyException($exception),
            ErrorSeverity::from($severity),
            OperationContext::from($context),
            $errorCode,
            $errorMessage,
            $apiRequestUrl,
            $apiRequest,
            $apiResponse,
            $stackTrace
        );
    }

    public static function clearLogs(): void
    {
        self::getLoggerInstance()->clearLogs();
    }
}
