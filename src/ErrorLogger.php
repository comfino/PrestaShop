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
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Configuration\ConfigManager;

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
        string $errorPrefix,
        string $errorCode,
        string $errorMessage,
        ?string $apiRequestUrl = null,
        ?string $apiRequest = null,
        ?string $apiResponse = null,
        ?string $stackTrace = null
    ): void {
        if ($exception instanceof ResponseValidationError || $exception instanceof AuthorizationError) {
            /* - Don't collect validation errors - validation errors are already collected at API side (response with status code 400).
               - Don't collect authorization errors caused by empty or wrong API key (response with status code 401). */
            return;
        }

        self::getLoggerInstance()->sendError(
            $errorPrefix, $errorCode, $errorMessage, $apiRequestUrl, $apiRequest, $apiResponse, $stackTrace
        );
    }
}
