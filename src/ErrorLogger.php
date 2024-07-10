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

use Comfino\ErrorLogger\StorageAdapter;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ErrorLogger
{
    /** @var Common\Backend\ErrorLogger */
    private static $errorLogger;
    /** @var string */
    private static $logFilePath;

    public static function init(\PaymentModule $module): void
    {
        static $initialized = false;

        if (!$initialized) {
            self::$errorLogger = self::getLoggerInstance($module);
            self::$errorLogger->init();

            self::$logFilePath = _PS_MODULE_DIR_ . $module->name . '/var/log/errors.log';

            $initialized = true;
        }
    }

    public static function getLoggerInstance(\PaymentModule $module): Common\Backend\ErrorLogger
    {
        return Common\Backend\ErrorLogger::getInstance(
            \Tools::getShopDomain(),
            'PrestaShop',
            'modules/' . $module->name,
            ConfigManager::getEnvironmentInfo(),
            ApiClient::getInstance(),
            new StorageAdapter()
        );
    }

    public static function sendError(
        string  $errorPrefix,
        string  $errorCode,
        string  $errorMessage,
        ?string $apiRequestUrl = null,
        ?string $apiRequest = null,
        ?string $apiResponse = null,
        ?string $stackTrace = null
    ): void {
        self::$errorLogger->sendError(
            $errorPrefix, $errorCode, $errorMessage, $apiRequestUrl, $apiRequest, $apiResponse, $stackTrace
        );
    }

    public static function logError(string $errorPrefix, string $errorMessage): void
    {
        @file_put_contents(
            self::$logFilePath,
            '[' . date('Y-m-d H:i:s') . "] $errorPrefix: $errorMessage\n",
            FILE_APPEND
        );
    }

    public static function getErrorLog(int $numLines): string
    {
        return self::$errorLogger->getErrorLog(self::$logFilePath, $numLines);
    }
}
