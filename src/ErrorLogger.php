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

require_once _PS_MODULE_DIR_ . 'comfino/src/ShopPluginError.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Tools.php';

use Comfino\ShopPluginError;

class ErrorLogger
{
    /** Maximum size of the log file before it is rotated. */
    const LOG_MAX_SIZE = 1048576;

    /** Names of JSON fields carrying personal data which must never be written to a local log file. */
    const REDACTED_FIELDS = [
        'firstName',
        'lastName',
        'email',
        'phoneNumber',
        'taxId',
        'ip',
        'street',
        'buildingNumber',
        'apartmentNumber',
        'postalCode',
        'city',
    ];

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
        $log_file_path = self::getLogFilePath();
        $log_entry = '[' . date('Y-m-d H:i:s') . '] ' . $error_prefix . ': '
            . self::redactSensitiveData($error_message) . "\n";

        if ($log_file_path === '') {
            /* The log directory could not be prepared - fall back to the PHP error log instead of losing
               the entry silently. */
            error_log('Comfino: unable to prepare the log directory. ' . rtrim($log_entry));

            return;
        }

        self::rotateLog($log_file_path);

        if (file_put_contents($log_file_path, $log_entry, FILE_APPEND | LOCK_EX) === false) {
            error_log("Comfino: unable to write to $log_file_path. " . rtrim($log_entry));
        }
    }

    /**
     * Returns the absolute path of the plugin log file. The file is kept outside of the document root, so it is
     * never served as a static asset regardless of the web server in use.
     *
     * @return string Empty string when the log directory could not be prepared.
     */
    public static function getLogFilePath()
    {
        static $log_file_path = null;

        if ($log_file_path !== null) {
            return $log_file_path;
        }

        $log_dir = self::getLogDirectory();

        if ($log_dir === '') {
            $log_file_path = '';

            return $log_file_path;
        }

        $log_file_path = $log_dir . '/payment_log.log';

        self::migrateLegacyLog($log_file_path);

        return $log_file_path;
    }

    /**
     * Creates (on first use) and returns the directory holding the plugin's log files. It sits outside the
     * document root, so nothing written there is served as a static asset.
     *
     * @return string Empty string when the directory could not be prepared.
     */
    public static function getLogDirectory()
    {
        static $log_dir = null;

        if ($log_dir !== null) {
            return $log_dir;
        }

        $log_dir = rtrim(_PS_ROOT_DIR_, '/\\') . (defined('COMFINO_PS_17') && COMFINO_PS_17 ? '/var/logs' : '/log') .
            '/comfino';

        if (!is_dir($log_dir) && !mkdir($log_dir, 0750, true) && !is_dir($log_dir)) {
            $log_dir = '';

            return $log_dir;
        }

        self::protectLogDirectory($log_dir);

        return $log_dir;
    }

    /**
     * Writes a deny-all .htaccess into the log directory as an extra layer of protection for Apache setups where
     * the directory could be reachable.
     *
     * @param string $log_dir
     *
     * @return void
     */
    private static function protectLogDirectory($log_dir)
    {
        $htaccess_path = $log_dir . '/.htaccess';

        if (file_exists($htaccess_path)) {
            return;
        }

        file_put_contents(
            $htaccess_path,
            "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n"
        );
    }

    /**
     * Removes the pre-3.6.0 log file from the publicly served module directory, preserving its content when it is
     * small enough to be worth keeping.
     *
     * @param string $log_file_path
     *
     * @return void
     */
    private static function migrateLegacyLog($log_file_path)
    {
        $legacy_log_path = _PS_MODULE_DIR_ . 'comfino/payment_log.log';

        if (!file_exists($legacy_log_path)) {
            return;
        }

        if (filesize($legacy_log_path) <= self::LOG_MAX_SIZE &&
            ($legacy_content = file_get_contents($legacy_log_path)) !== false
        ) {
            file_put_contents($log_file_path, $legacy_content, FILE_APPEND | LOCK_EX);
        }

        unlink($legacy_log_path);
    }

    /**
     * @param string $log_file_path
     *
     * @return void
     */
    private static function rotateLog($log_file_path)
    {
        clearstatcache(true, $log_file_path);

        if (!file_exists($log_file_path) || filesize($log_file_path) < self::LOG_MAX_SIZE) {
            return;
        }

        $rotated_log_path = $log_file_path . '.1';

        if (file_exists($rotated_log_path)) {
            unlink($rotated_log_path);
        }

        rename($log_file_path, $rotated_log_path);
    }

    /**
     * Strips secrets and personal data from a message before it is persisted in the local log file.
     *
     * @param string $message
     *
     * @return string
     */
    private static function redactSensitiveData($message)
    {
        //The API key may appear as a bare header line or inside a comma-separated list of headers.
        $message = preg_replace('/(Api-Key:\s*)[^,\r\n]+/i', '$1[REDACTED]', $message);

        // Personal data carried by the JSON request bodies attached to API error reports.
        return preg_replace(
            '/("(?:' . implode('|', self::REDACTED_FIELDS) . ')"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/i',
            '$1"[REDACTED]"',
            $message
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
        $http_status_code = 0,
        $api_request_url = null,
        $api_request = null,
        $api_response = null,
        $stack_trace = null
    ) {
        if (preg_match('/Error .*in |Exception .*in /', $error_message) &&
            strpos($error_message, 'modules/comfino') === false
        ) {
            // Ignore all errors and exceptions outside the plugin code.
            return;
        }

        if ($http_status_code === 400) {
            // Don't collect validation errors - validation errors are already collected at API side.
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
                'server_software' => Tools::getServerValue('SERVER_SOFTWARE'),
                'server_name' => Tools::getServerValue('SERVER_NAME'),
                'server_addr' => Tools::getServerValue('SERVER_ADDR'),
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
        $log_file_path = self::getLogFilePath();

        if ($log_file_path !== '' && file_exists($log_file_path)) {
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
            $exception->getCode(),
            $exception->getMessage(),
            0,
            null,
            null,
            null,
            $exception->getTraceAsString()
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
