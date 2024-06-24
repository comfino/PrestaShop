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

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Extended\Api\Client;
use Psr\Http\Client\NetworkExceptionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ApiClient
{
    /** @var Client */
    private static $apiClient;

    public static function getInstance(?bool $sandboxMode = null, ?string $apiKey = null): Client
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
                self::getApiHost(),
                \Context::getContext()->language->iso_code,
                [CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 3]
            );
        } else {
            self::$apiClient->setApiKey($apiKey);
            self::$apiClient->setApiLanguage(\Context::getContext()->language->iso_code);
        }

        return self::$apiClient;
    }

    public static function processApiError(string $errorPrefix, \Throwable $exception): void
    {
        if ($exception instanceof RequestValidationError || $exception instanceof ResponseValidationError
            || $exception instanceof AuthorizationError || $exception instanceof AccessDenied
            || $exception instanceof ServiceUnavailable
        ) {
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();

            if ($exception instanceof ResponseValidationError || $exception instanceof ServiceUnavailable) {
                $responseBody = $exception->getResponseBody();
            } else {
                $responseBody = null;
            }
        } elseif ($exception instanceof NetworkExceptionInterface) {
            $exception->getRequest()->getBody()->rewind();

            $url = $exception->getRequest()->getRequestTarget();
            $requestBody = $exception->getRequest()->getBody()->getContents();
            $responseBody = null;
        } else {
            $url = null;
            $requestBody = null;
            $responseBody = null;
        }

        ErrorLogger::sendError(
            $errorPrefix,
            $exception->getCode(),
            $exception->getMessage(),
            $url !== '' ? $url : null,
            $requestBody !== '' ? $requestBody : null,
            $responseBody !== '' ? $responseBody : null,
            $exception->getTraceAsString()
        );
    }

    public static function getLogoUrl(\PaymentModule $module): string
    {
        return self::getApiHost(self::getInstance()->getApiHost())
            . '/v1/get-logo-url?auth='
            . FrontendManager::getPaywallRenderer($module)->getLogoAuthHash('PS', _PS_VERSION_, COMFINO_VERSION);
    }

    public static function getPaywallLogoUrl(\PaymentModule $module): string
    {
        return self::getApiHost(self::getInstance()->getApiHost())
            . '/v1/get-paywall-logo?auth='
            . FrontendManager::getPaywallRenderer($module)->getPaywallLogoAuthHash(
                'PS', _PS_VERSION_, COMFINO_VERSION, self::getInstance()->getApiKey(), ConfigManager::getWidgetKey()
            );
    }

    public static function getWidgetScriptUrl(): string
    {
        if (self::isDevEnv() && getenv('COMFINO_DEV_WIDGET_SCRIPT_URL')) {
            return getenv('COMFINO_DEV_WIDGET_SCRIPT_URL');
        }

        $widgetScriptUrl = ConfigManager::isSandboxMode() ? 'https://widget.craty.pl' : 'https://widget.comfino.pl';
        $widgetProdScriptVersion = ConfigManager::getConfigurationValue('COMFINO_WIDGET_PROD_SCRIPT_VERSION');

        if (empty($widgetProdScriptVersion)) {
            $widgetScriptUrl .= '/comfino.min.js';
        } else {
            $widgetScriptUrl .= ('/' . trim($widgetProdScriptVersion, '/'));
        }

        return $widgetScriptUrl;
    }

    private static function getApiHost(?string $apiHost = null): ?string
    {
        if (self::isDevEnv() && getenv('COMFINO_DEV_API_HOST')) {
            return getenv('COMFINO_DEV_API_HOST');
        }

        return $apiHost;
    }

    private static function isDevEnv(): bool
    {
        return getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN');
    }
}
