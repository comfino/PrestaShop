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
     * @param string $error_prefix
     * @param string $error_message
     *
     * @return void
     */
    public static function logError($error_prefix, $error_message)
    {
        @file_put_contents(
            _PS_MODULE_DIR_ . 'comfino/payment_log.log',
            '[' . date('Y-m-d H:i:s') . "] $error_prefix: $error_message\n",
            FILE_APPEND
        );
    }

    /**
     * @param string $error_prefix
     * @param string $error_code
     * @param string $error_message
     * @param string|null $api_request_url
     * @param string|null $api_request
     * @param string|null $api_response
     * @param string|null $stack_trace
     *
     * @return void
     */
    public static function sendError(
        $error_prefix,
        $error_code,
        $error_message,
        $api_request_url = null,
        $api_request = null,
        $api_response = null,
        $stack_trace = null
    ) {
        if (preg_match('/Error .*in \/|Exception .*in \//', $error_message) &&
            strpos($error_message, 'modules/comfino') === false
        ) {
            // Ignore all errors and exceptions outside the plugin code.
            return;
        }

        $error = new ShopPluginError(
            \Tools::getShopDomain(),
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
                'database_version' => \Db::getInstance()->getVersion(),
            ],
            $error_code,
            "$error_prefix: $error_message",
            $api_request_url,
            $api_request,
            $api_response,
            $stack_trace
        );

        if (!Api::sendLoggedError($error)) {
            $request_info = [];

            if ($api_request_url !== null) {
                $request_info[] = "API URL: $api_request_url";
            }

            if ($api_request !== null) {
                $request_info[] = "API request: $api_request";
            }

            if ($api_response !== null) {
                $request_info[] = "API response: $api_response";
            }

            if (count($request_info)) {
                $error_message .= "\n" . implode("\n", $request_info);
            }

            if ($stack_trace !== null) {
                $error_message .= "\nStack trace: $stack_trace";
            }

            self::logError($error_prefix, $error_message);
        }
    }

    /**
     * @param int $num_lines
     *
     * @return string
     */
    public static function getErrorLog($num_lines)
    {
        $errors_log = '';
        $log_file_path = _PS_MODULE_DIR_ . 'comfino/payment_log.log';

        if (file_exists($log_file_path)) {
            $file = new \SplFileObject($log_file_path, 'r');
            $file->seek(PHP_INT_MAX);

            $last_line = $file->key();

            $lines = new \LimitIterator(
                $file,
                $last_line > $num_lines ? $last_line - $num_lines : 0,
                $last_line ?: 1
            );

            $errors_log = implode('', iterator_to_array($lines));
        }

        return $errors_log;
    }

    /**
     * @param int $err_no
     * @param string $err_msg
     * @param string $file
     * @param int $line
     *
     * @return bool
     */
    public static function errorHandler($err_no, $err_msg, $file, $line)
    {
        $error_type = self::getErrorTypeName($err_no);

        if (strpos($error_type, 'E_USER_') === false && strpos($error_type, 'NOTICE') === false) {
            self::sendError("Error $error_type in $file:$line", $err_no, $err_msg);
        }

        return false;
    }

    /**
     * @param \Throwable $exception
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
        if (getenv('COMFINO_DEBUG') === 'TRUE') {
            // Disable custom errors handling if plugin is in debug mode.
            return;
        }

        static $initialized = false;

        if (!$initialized) {
            set_error_handler([__CLASS__, 'errorHandler'], E_ERROR | E_RECOVERABLE_ERROR | E_PARSE);
            set_exception_handler([__CLASS__, 'exceptionHandler']);
            register_shutdown_function([__CLASS__, 'shutdown']);

            $initialized = true;
        }
    }

    public static function shutdown()
    {
        if (($error = error_get_last()) !== null && ($error['type'] & (E_ERROR | E_RECOVERABLE_ERROR | E_PARSE))) {
            $error_type = self::getErrorTypeName($error['type']);
            self::sendError("Error $error_type in $error[file]:$error[line]", $error['type'], $error['message']);
        }

        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * @param int $error_type
     *
     * @return string
     */
    private static function getErrorTypeName($error_type)
    {
        return array_key_exists($error_type, self::ERROR_TYPES) ? self::ERROR_TYPES[$error_type] : 'UNKNOWN';
    }
}
