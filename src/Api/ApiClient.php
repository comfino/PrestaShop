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

use Comfino\Api\Exception\AuthorizationError;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\ErrorLogger;
use ComfinoExternal\Psr\Http\Client\NetworkExceptionInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ApiClient
{
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
                self::getApiHost(),
                \Context::getContext()->language->iso_code,
                ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_TIMEOUT', 1),
                ConfigManager::getConfigurationValue('COMFINO_API_TIMEOUT', 3),
                ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_NUM_ATTEMPTS', 3)
            );

            self::$apiClient->addCustomHeader('Comfino-Build-Timestamp', (string) COMFINO_BUILD_TS);
        } else {
            self::$apiClient->setCustomApiHost(self::getApiHost());
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

    public static function processApiError(string $errorPrefix, \Throwable $exception): void
    {
        if ($exception instanceof AuthorizationError) {
            // Don't collect authorization errors caused by empty or wrong API key (response with status code 401).
            return;
        }

        if ($exception instanceof HttpErrorExceptionInterface) {
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();
            $responseBody = $exception->getResponseBody();
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

    public static function getLogoUrl(): string
    {
        return self::getApiHost(
            self::getInstance()->getApiHost()) . '/v1/get-logo-url?auth=' . FrontendHelper::getLogoAuthHash(
                'PS', _PS_VERSION_, COMFINO_VERSION, COMFINO_BUILD_TS
            );
    }

    public static function getPaywallLogoUrl(): string
    {
        return self::getApiHost(
            self::getInstance()->getApiHost()) . '/v1/get-paywall-logo?auth=' . FrontendHelper::getPaywallLogoAuthHash(
                'PS',
                _PS_VERSION_,
                COMFINO_VERSION,
                self::getInstance()->getApiKey(),
                ConfigManager::getWidgetKey(),
                COMFINO_BUILD_TS
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

    public static function isDevEnv(): bool
    {
        return ((string) getenv('COMFINO_DEV')) === ('PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN'));
    }

    private static function getApiHost(?string $apiHost = null): ?string
    {
        if (self::isDevEnv() && getenv('COMFINO_DEV_API_HOST')) {
            return getenv('COMFINO_DEV_API_HOST');
        }

        return $apiHost;
    }
}
