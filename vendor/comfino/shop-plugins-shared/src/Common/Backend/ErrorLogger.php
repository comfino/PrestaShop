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

namespace Comfino\Common\Backend;

use Comfino\Common\Backend\Logger\StorageAdapterInterface;
use Comfino\Extended\Api\Client;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;

final class ErrorLogger
{
    /**
     * @readonly
     * @var string
     */
    private $host;
    /**
     * @readonly
     * @var string
     */
    private $platform;
    /**
     * @readonly
     * @var string
     */
    private $modulePath;
    /**
     * @readonly
     * @var mixed[]
     */
    private $environment;
    /**
     * @readonly
     * @var \Comfino\Extended\Api\Client
     */
    private $apiClient;
    /**
     * @readonly
     * @var \Comfino\Common\Backend\Logger\StorageAdapterInterface
     */
    private $storageAdapter;
    private const ERROR_TYPES = [
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
     * @var $this|null
     */
    private static $instance;

    public static function getInstance(string $host, string $platform, string $modulePath, array $environment, Client $apiClient, StorageAdapterInterface $storageAdapter): self
    {
        if (self::$instance === null) {
            self::$instance = new self($host, $platform, $modulePath, $environment, $apiClient, $storageAdapter);
        }

        return self::$instance;
    }

    private function __construct(string $host, string $platform, string $modulePath, array $environment, Client $apiClient, StorageAdapterInterface $storageAdapter)
    {
        $this->host = $host;
        $this->platform = $platform;
        $this->modulePath = $modulePath;
        $this->environment = $environment;
        $this->apiClient = $apiClient;
        $this->storageAdapter = $storageAdapter;
    }

    public function sendError(
        string $errorPrefix,
        string $errorCode,
        string $errorMessage,
        ?string $apiRequestUrl = null,
        ?string $apiRequest = null,
        ?string $apiResponse = null,
        ?string $stackTrace = null
    ): void
    {
        if (preg_match('/Error .*in \/|Exception .*in \//', $errorMessage) && strpos($errorMessage, $this->modulePath) === false
        ) {
            // Ignore all errors and exceptions outside the plugin code.
            return;
        }

        if (getenv('COMFINO_DEBUG') === 'TRUE') {
            // Disable sending errors to the Comfino API if plugin is in debug mode.
            $errorsSendingDisabled = true;
        } else {
            $errorsSendingDisabled = false;
        }

        $error = new ShopPluginError(
            $this->host,
            $this->platform,
            $this->environment,
            $errorCode,
            "$errorPrefix: $errorMessage",
            $apiRequestUrl,
            $apiRequest,
            $apiResponse,
            $stackTrace
        );

        if ($errorsSendingDisabled || !$this->apiClient->sendLoggedError($error)) {
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

            $this->storageAdapter->save($errorPrefix, $errorMessage);
        }
    }

    public function getErrorLog(string $logFilePath, int $numLines)
    {
        $errorsLog = '';

        if (file_exists($logFilePath)) {
            $file = new \SplFileObject($logFilePath, 'r');
            $file->seek(PHP_INT_MAX);

            $lastLine = $file->key();

            $lines = new \LimitIterator(
                $file,
                $lastLine > $numLines ? $lastLine - $numLines : 0,
                $lastLine ?: 1
            );

            $errorsLog = implode('', iterator_to_array($lines));
        }

        return $errorsLog;
    }

    public function errorHandler($errNo, $errMsg, $file, $line)
    {
        $errorType = $this->getErrorTypeName($errNo);

        if (strpos($errorType, 'E_USER_') === false && strpos($errorType, 'NOTICE') === false) {
            $this->sendError("Error $errorType in $file:$line", $errNo, $errMsg);
        }

        return false;
    }

    public function exceptionHandler(\Throwable $exception): void
    {
        $this->sendError(
            'Exception ' . get_class($exception) . " in {$exception->getFile()}:{$exception->getLine()}",
            $exception->getCode(), $exception->getMessage(),
            null, null, null, $exception->getTraceAsString()
        );
    }

    public function init(): void
    {
        if (getenv('COMFINO_DEBUG') === 'TRUE') {
            // Disable custom errors handling if plugin is in debug mode.
            return;
        }

        static $initialized = false;

        if (!$initialized) {
            set_error_handler([$this, 'errorHandler'], E_ERROR | E_RECOVERABLE_ERROR | E_PARSE);
            set_exception_handler([$this, 'exceptionHandler']);
            register_shutdown_function([$this, 'shutdown']);

            $initialized = true;
        }
    }

    public function shutdown(): void
    {
        if (($error = error_get_last()) !== null && ($error['type'] & (E_ERROR | E_RECOVERABLE_ERROR | E_PARSE))) {
            $errorType = $this->getErrorTypeName($error['type']);
            $this->sendError("Error $errorType in $error[file]:$error[line]", $error['type'], $error['message']);
        }

        restore_error_handler();
        restore_exception_handler();
    }

    private function getErrorTypeName(int $errorType): string
    {
        return array_key_exists($errorType, self::ERROR_TYPES) ? self::ERROR_TYPES[$errorType] : 'UNKNOWN';
    }
}
