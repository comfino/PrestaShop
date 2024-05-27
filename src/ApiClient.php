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
    private static $api_client;

    /** @var string */
    public static $paywall_frontend_style_url;

    /** @var string */
    private static $widget_script_url;

    /** @var string */
    private static $widget_key;

    public static function getInstance(?bool $sandbox_mode = null, ?string $api_key = null): Client
    {
        if (self::$api_client === null) {
            if ($sandbox_mode === null) {
                $sandbox_mode = ConfigManager::isSandboxMode();
            }

            if ($api_key === null) {
                if ($sandbox_mode) {
                    $api_key = ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY');
                } else {
                    $api_key = ConfigManager::getConfigurationValue('COMFINO_API_KEY');
                }
            }

            self::$api_client = (new ApiClientFactory())->createClient(
                $api_key,
                sprintf(
                    'PS Comfino [%s], PS [%s], SF [%s], PHP [%s], %s',
                    ...array_merge(
                        ConfigManager::getEnvironmentInfo([
                            'plugin_version',
                            'shop_version',
                            'symfony_version',
                            'php_version',
                        ]),
                        [\Tools::getShopDomain()]
                    )
                ),
                self::getApiHost(),
                \Context::getContext()->language->iso_code,
                [CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 3]
            );
        }

        return self::$api_client;
    }

    public static function processApiError(string $errorPrefix, \Throwable $exception): void
    {
        if ($exception instanceof RequestValidationError | $exception instanceof ResponseValidationError
            | $exception instanceof AuthorizationError | $exception instanceof AccessDenied
            | $exception instanceof ServiceUnavailable
        ) {
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();
            $responseBody = $exception->getResponseBody();
        } elseif ($exception instanceof NetworkExceptionInterface) {
            $exception->getRequest()->getBody()->rewind();

            $url = $exception->getRequest()->getRequestTarget();
            $requestBody = $exception->getRequest()->getBody()->getContents();
            $responseBody = '';
        } else {
            $url = '';
            $requestBody = '';
            $responseBody = '';
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
        return self::getApiHost(true, self::getInstance()->getApiHost())
            . '/v1/get-logo-url?auth=' . self::getLogoAuthHash();
    }

    public static function getPaywallLogoUrl(): string
    {
        return self::getApiHost(true, self::getInstance()->getApiHost())
            . '/v1/get-paywall-logo?auth=' . self::getLogoAuthHash(true);
    }

    public static function getWidgetScriptUrl(): string
    {
        if (getenv('COMFINO_DEV') && getenv('PS_DOMAIN') && getenv('COMFINO_DEV_WIDGET_SCRIPT_URL')
            && getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')
        ) {
            return getenv('COMFINO_DEV_WIDGET_SCRIPT_URL');
        }

        $widget_script_url = ConfigManager::isSandboxMode() ? 'https://widget.craty.pl' : 'https://widget.comfino.pl';
        $widget_prod_script_version = ConfigManager::getConfigurationValue('COMFINO_WIDGET_PROD_SCRIPT_VERSION');

        if (empty($widget_prod_script_version)) {
            $widget_script_url .= '/comfino.min.js';
        } else {
            $widget_script_url .= ('/' . trim($widget_prod_script_version, '/'));
        }

        return $widget_script_url;
    }

    private static function getApiHost(bool $frontend_host = false, ?string $api_host = null): ?string
    {
        if (getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')) {
            if ($frontend_host) {
                if (getenv('COMFINO_DEV_API_HOST_FRONTEND')) {
                    return getenv('COMFINO_DEV_API_HOST_FRONTEND');
                }
            } elseif (getenv('COMFINO_DEV_API_HOST_BACKEND')) {
                return getenv('COMFINO_DEV_API_HOST_BACKEND');
            }
        }

        return $api_host;
    }

    private static function getLogoAuthHash(bool $paywallLogo = false): string
    {
        $platformVersion = array_map('intval', explode('.', _PS_VERSION_));
        $pluginVersion = array_map('intval', explode('.', COMFINO_VERSION));
        $packedPlatformVersion = pack('c*', ...$platformVersion);
        $packedPluginVersion = pack('c*', ...$pluginVersion);
        $platformVersionLength = pack('c', strlen($packedPlatformVersion));
        $pluginVersionLength = pack('c', strlen($packedPluginVersion));

        $authHash = "PS$platformVersionLength$pluginVersionLength$packedPlatformVersion$packedPluginVersion";

        if ($paywallLogo) {
            $authHash .= self::$widget_key;
            $authHash .= hash_hmac('sha3-256', $authHash, self::getInstance()->getApiKey(), true);
        }

        return urlencode(base64_encode($authHash));
    }

    //-----------------------------------------

    /**
     * @return string
     */
    public static function getPaywallFrontendStyleUrl()
    {
        if (getenv('COMFINO_DEV') && getenv('COMFINO_DEV_PAYWALL_FRONTEND_STYLE_URL')
            && getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')
        ) {
            return getenv('COMFINO_DEV_PAYWALL_FRONTEND_STYLE_URL');
        }

        return self::$paywall_frontend_style_url;
    }
}
