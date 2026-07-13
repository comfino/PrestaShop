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

namespace Comfino\Api;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Common\Exception\ConnectionTimeout;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\Main;
use ComfinoExternal\Psr\Http\Client\NetworkExceptionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ApiClient
{
    private const CHECKOUT_TRACK_ID_COOKIE = 'comfino_checkout_track_id';
    private const CHECKOUT_TRACK_ID_COOKIE_TTL = 900;
    private const CHECKOUT_TRACK_ID_PATTERN = '/^[A-Za-z0-9_.:-]{1,128}$/';

    /** @var \Comfino\Common\Api\Client */
    private static $apiClient;

    public static function getInstance(?bool $sandboxMode = null, ?string $apiKey = null): \Comfino\Common\Api\Client
    {
        if ($sandboxMode === null) {
            $sandboxMode = ConfigManager::isSandboxMode();
        }

        if ($apiKey === null) {
            if ($sandboxMode) {
                $apiKey = ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY');
            } else {
                $apiKey = ConfigManager::getConfigurationValue('COMFINO_API_KEY');
            }
        }

        if (self::$apiClient === null) {
            self::$apiClient = (new ApiClientFactory())->createClient(
                $apiKey,
                sprintf(
                    'PS Comfino [%s], PS [%s], SF [%s], PHP [%s], %s',
                    ...array_merge(
                        array_values(ConfigManager::getEnvironmentInfo([
                            'plugin_version',
                            'shop_version',
                            'symfony_version',
                            'php_version',
                        ])),
                        [\Tools::getShopDomain()]
                    )
                ),
                ConfigManager::getApiHost(),
                \Context::getContext()->language->iso_code,
                ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_TIMEOUT', 3),
                ConfigManager::getConfigurationValue('COMFINO_API_TIMEOUT', 5),
                ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_NUM_ATTEMPTS', 3)
            );

            self::$apiClient->addCustomHeader('Comfino-Build-Timestamp', (string) COMFINO_BUILD_TS);
            self::$apiClient->setClientHostName(\Tools::getShopDomain());
        } else {
            self::$apiClient->setCustomApiHost(ConfigManager::getApiHost());
            self::$apiClient->setApiKey($apiKey);
            self::$apiClient->setApiLanguage(\Context::getContext()->language->iso_code);
        }

        if ($sandboxMode) {
            self::$apiClient->enableSandboxMode();
        } else {
            self::$apiClient->disableSandboxMode();
        }

        return self::$apiClient;
    }

    /**
     * Pins this instance's trackId to the checkout-scoped cookie value, so a checkout-page paywall render and the
     * later separate order-create request share the same trackId. Checkout-only: never call this from product-page
     * rendering, where a fresh trackId per page load is still correct behavior.
     *
     * @return void
     */
    public static function pinCheckoutTrackId()
    {
        $client = self::getInstance();

        if (isset($_COOKIE[self::CHECKOUT_TRACK_ID_COOKIE]) &&
            preg_match(self::CHECKOUT_TRACK_ID_PATTERN, $_COOKIE[self::CHECKOUT_TRACK_ID_COOKIE]) === 1
        ) {
            $client->setTrackId($_COOKIE[self::CHECKOUT_TRACK_ID_COOKIE]);
        }

        $trackId = $client->getTrackId();

        if (!headers_sent()) {
            $expires = time() + self::CHECKOUT_TRACK_ID_COOKIE_TTL;

            if (PHP_VERSION_ID >= 70300) {
                setcookie(self::CHECKOUT_TRACK_ID_COOKIE, $trackId, [
                    'expires' => $expires,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                /* PHP 7.1/7.2 target: setcookie()'s array-options 4th param (needed for a clean SameSite flag)
                   requires PHP 7.3+. SameSite is set via the well-known path-suffix workaround instead. Since
                   PHP 8.1, setcookie() rejects ";"/" " in the "path" arg, so this branch must stay 7.3-excluded. */
                setcookie(
                    self::CHECKOUT_TRACK_ID_COOKIE,
                    $trackId,
                    $expires,
                    '/; SameSite=Lax',
                    '',
                    true,
                    true
                );
            }
        }
    }

    public static function processApiError(
        string $errorPrefix,
        \Throwable $exception,
        string $context = OperationContext::ApiCommunication
    ): array {
        $userErrorMessage = Main::translate(
            'There was a technical problem. Please try again in a moment and it should work!'
        );

        $statusCode = 500;
        $isTimeout = false;
        $connectAttemptIdx = 1;
        $connectionTimeout = ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_TIMEOUT', 3);
        $transferTimeout = ConfigManager::getConfigurationValue('COMFINO_API_TIMEOUT', 5);

        if ($exception instanceof HttpErrorExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();
            $responseBody = $exception->getResponseBody();

            if ($exception instanceof AccessDenied && $statusCode === 404) {
                $userErrorMessage = $exception->getMessage();
            } elseif ($exception instanceof ConnectionTimeout) {
                $isTimeout = true;
                $connectAttemptIdx = $exception->getConnectAttemptIdx();
                $connectionTimeout = $exception->getConnectionTimeout();
                $transferTimeout = $exception->getTransferTimeout();

                DebugLogger::logEvent(
                    '[API_TIMEOUT]',
                    $errorPrefix,
                    [
                        'exception' => $exception->getPrevious() !== null ? get_class($exception->getPrevious()) : '',
                        'code' => $exception->getPrevious() !== null ? $exception->getPrevious()->getCode() : 0,
                        'connect_attempt_idx' => $exception->getConnectAttemptIdx(),
                        'connection_timeout' => $exception->getConnectionTimeout(),
                        'transfer_timeout' => $exception->getTransferTimeout(),
                    ]
                );
            } elseif ($statusCode < 500) {
                $userErrorMessage = Main::translate(
                    'We have a configuration problem. The store is already working on a solution!'
                );
            } elseif ($statusCode < 504) {
                $userErrorMessage = Main::translate(
                    'It looks like we have an outage. We\'ll fix it as soon as possible!',
                    'comfino-payment-gateway'
                );
            }
        } elseif ($exception instanceof NetworkExceptionInterface) {
            $exception->getRequest()->getBody()->rewind();

            DebugLogger::logEvent('[API_NETWORK_ERROR]', $errorPrefix . " [{$exception->getMessage()}]");

            $url = $exception->getRequest()->getRequestTarget();
            $requestBody = $exception->getRequest()->getBody()->getContents();
            $responseBody = null;
        } else {
            $url = null;
            $requestBody = null;
            $responseBody = null;
        }

        DebugLogger::logEvent(
            '[API_ERROR]',
            $errorPrefix,
            [
                'exception' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine(),
                'error_trace' => $exception->getTraceAsString(),
            ]
        );

        ErrorLogger::sendError(
            $exception,
            $context,
            (string) $exception->getCode(),
            $exception->getMessage(),
            $url !== '' ? $url : null,
            $requestBody !== '' ? $requestBody : null,
            $responseBody !== '' ? $responseBody : null,
            $exception->getTraceAsString()
        );

        return [
            'title' => $userErrorMessage,
            'error_details' => FrontendHelper::prepareErrorDetails(
                $userErrorMessage,
                $statusCode,
                ConfigManager::useDevEnvVars(),
                $exception,
                $isTimeout,
                $connectAttemptIdx,
                $connectionTimeout,
                $transferTimeout,
                $url,
                $requestBody,
                $responseBody
            ),
        ];
    }
}
