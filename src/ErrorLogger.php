<?php
/**
 * 2007-2023 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'ShopPluginError.php';

class ErrorLogger
{
    const ERROR_TYPES = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
    ];

    /**
     * @param string $errorPrefix
     * @param string $errorMessage
     *
     * @return void
     */
    public static function logError($errorPrefix, $errorMessage)
    {
        @file_put_contents(
            _PS_MODULE_DIR_ . 'comfino/payment_log.log',
            '[' . date('Y-m-d H:i:s') . "] $errorPrefix: $errorMessage\n",
            FILE_APPEND
        );
    }

    /**
     * @param string $errorPrefix
     * @param string $errorCode
     * @param string $errorMessage
     * @param string|null $apiRequestUrl
     * @param string|null $apiRequest
     * @param string|null $apiResponse
     * @param string|null $stackTrace
     *
     * @return void
     */
    public static function sendError(
        $errorPrefix,
        $errorCode,
        $errorMessage,
        $apiRequestUrl = null,
        $apiRequest = null,
        $apiResponse = null,
        $stackTrace = null
    ) {
        $error = new ShopPluginError(
            Tools::getShopDomain(),
            'PrestaShop',
            [
                'plugin_version' => COMFINO_VERSION,
                'shop_version' => _PS_VERSION_,
                'symfony_version' => COMFINO_PS_17 && class_exists('\Symfony\Component\HttpKernel\Kernel')
                    ? \Symfony\Component\HttpKernel\Kernel::VERSION
                    : 'n/a',
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'],
                'server_name' => $_SERVER['SERVER_NAME'],
                'server_addr' => $_SERVER['SERVER_ADDR'],
                'database_version' => Db::getInstance()->getVersion(),
            ],
            $errorCode,
            "$errorPrefix: $errorMessage",
            $apiRequestUrl,
            $apiRequest,
            $apiResponse,
            $stackTrace
        );

        if (!ComfinoApi::sendLoggedError($error)) {
            $requestInfo = [];

            if ($apiRequestUrl !== null) {
                $requestInfo[] = "API URL: $apiRequestUrl";
            }

            if ($apiRequest !== null) {
                $requestInfo[] = "API request: $apiRequest";
            }

            if ($apiResponse !== null) {
                $requestInfo[] = "API response: $apiResponse";
            }

            if (count($requestInfo)) {
                $errorMessage .= "\n" . implode("\n", $requestInfo);
            }

            if ($stackTrace !== null) {
                $errorMessage .= "\nStack trace: $stackTrace";
            }

            self::logError($errorPrefix, $errorMessage);
        }
    }

    /**
     * @param int $numLines
     *
     * @return string
     */
    public static function getErrorLog($numLines)
    {
        $errorsLog = '';
        $logFilePath = _PS_MODULE_DIR_ . 'comfino/payment_log.log';

        if (file_exists($logFilePath)) {
            $file = new SplFileObject($logFilePath, 'r');
            $file->seek(PHP_INT_MAX);
            $lastLine = $file->key();
            $lines = new LimitIterator(
                $file,
                $lastLine > $numLines ? $lastLine - $numLines : 0,
                $lastLine ?: 1
            );
            $errorsLog = implode('', iterator_to_array($lines));
        }

        return $errorsLog;
    }

    /**
     * @param int $errNo
     * @param string $errMsg
     * @param string $file
     * @param int $line
     *
     * @return bool
     */
    public static function errorHandler($errNo, $errMsg, $file, $line)
    {
        $errorType = self::getErrorTypeName($errNo);
        self::sendError("Error $errorType in $file:$line", $errNo, $errMsg);

        return false;
    }

    /**
     * @param Throwable $exception
     *
     * @return void
     */
    public static function exceptionHandler($exception)
    {
        self::sendError(
            'Exception ' . get_class($exception) . " in {$exception->getFile()}:{$exception->getLine()}",
            $exception->getCode(), $exception->getMessage(),
            null, null, null, $exception->getTraceAsString()
        );
    }

    public static function init()
    {
        static $initialized = false;

        if (!$initialized) {
            set_error_handler(['ErrorLogger', 'errorHandler'], E_ERROR | E_RECOVERABLE_ERROR | E_PARSE);
            set_exception_handler(['ErrorLogger', 'exceptionHandler']);
            register_shutdown_function(['ErrorLogger', 'shutdown']);

            $initialized = true;
        }
    }

    public static function shutdown()
    {
        if (($error = error_get_last()) !== null && ($error['type'] & (E_ERROR | E_RECOVERABLE_ERROR | E_PARSE))) {
            $errorType = self::getErrorTypeName($error['type']);
            self::sendError("Error $errorType in $error[file]:$error[line]", $error['type'], $error['message']);
        }

        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * @param int $errorType
     *
     * @return string
     */
    private static function getErrorTypeName($errorType)
    {
        return array_key_exists($errorType, self::ERROR_TYPES) ? self::ERROR_TYPES[$errorType] : 'UNKNOWN';
    }
}
