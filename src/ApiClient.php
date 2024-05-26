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
    private const COMFINO_PRODUCTION_API_HOST = 'https://api-ecommerce.comfino.pl';
    private const COMFINO_SANDBOX_API_HOST = 'https://api-ecommerce.ecraty.pl';

    const COMFINO_PAYWALL_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    const COMFINO_PAYWALL_SANDBOX_HOST = 'https://api-ecommerce.ecraty.pl';

    const COMFINO_PAYWALL_FRONTEND_JS_SANDBOX = 'https://widget.craty.pl/paywall-frontend.min.js';
    const COMFINO_PAYWALL_FRONTEND_JS_PRODUCTION = 'https://widget.comfino.pl/paywall-frontend.min.js';

    const COMFINO_PAYWALL_FRONTEND_CSS_SANDBOX = 'https://widget.craty.pl/css/paywall-frontend.css';
    const COMFINO_PAYWALL_FRONTEND_CSS_PRODUCTION = 'https://widget.comfino.pl/css/paywall-frontend.css';

    const COMFINO_WIDGET_JS_SANDBOX_HOST = 'https://widget.craty.pl';
    const COMFINO_WIDGET_JS_PRODUCTION_HOST = 'https://widget.comfino.pl';

    /** @var Client */
    private static $api_client;

    /** @var bool */
    private static $is_sandbox_mode;

    /** @var string */
    private static $api_host;

    /** @var string */
    private static $api_key;

    /** @var string */
    private static $api_paywall_host;

    /** @var string */
    public static $paywall_frontend_script_url;

    /** @var string */
    public static $paywall_frontend_style_url;

    /** @var string */
    private static $widget_script_url;

    /** @var string */
    private static $widget_key;

    public static function init(): void
    {
        self::$is_sandbox_mode = ConfigManager::getConfigurationValue('COMFINO_IS_SANDBOX');
        self::$widget_key = ConfigManager::getConfigurationValue('COMFINO_WIDGET_KEY');

        if (self::$is_sandbox_mode) {
            self::$api_host = self::COMFINO_SANDBOX_API_HOST;
            self::$api_key = ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY');
            self::$api_paywall_host = self::COMFINO_PAYWALL_SANDBOX_HOST;
            self::$paywall_frontend_script_url = self::COMFINO_PAYWALL_FRONTEND_JS_SANDBOX;
            self::$paywall_frontend_style_url = self::COMFINO_PAYWALL_FRONTEND_CSS_SANDBOX;
            self::$widget_script_url = self::COMFINO_WIDGET_JS_SANDBOX_HOST;

            $widget_dev_script_version = ConfigManager::getConfigurationValue('COMFINO_WIDGET_DEV_SCRIPT_VERSION');

            if (empty($widget_dev_script_version)) {
                self::$widget_script_url .= '/comfino.min.js';
            } else {
                self::$widget_script_url .= ('/' . trim($widget_dev_script_version, '/'));
            }
        } else {
            self::$api_host = self::COMFINO_PRODUCTION_API_HOST;
            self::$api_key = ConfigManager::getConfigurationValue('COMFINO_API_KEY');
            self::$api_paywall_host = self::COMFINO_PAYWALL_PRODUCTION_HOST;
            self::$paywall_frontend_script_url = self::COMFINO_PAYWALL_FRONTEND_JS_PRODUCTION;
            self::$paywall_frontend_style_url = self::COMFINO_PAYWALL_FRONTEND_CSS_PRODUCTION;
            self::$widget_script_url = self::COMFINO_WIDGET_JS_PRODUCTION_HOST;

            $widget_prod_script_version = ConfigManager::getConfigurationValue('COMFINO_WIDGET_PROD_SCRIPT_VERSION');

            if (empty($widget_prod_script_version)) {
                self::$widget_script_url .= '/comfino.min.js';
            } else {
                self::$widget_script_url .= ('/' . trim($widget_prod_script_version, '/'));
            }
        }
    }

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
            $authHash .= hash_hmac('sha3-256', $authHash, self::getApiKey(), true);
        }

        return urlencode(base64_encode($authHash));
    }

    //-----------------------------------------

    /**
     * @return string
     */
    public static function getApiKey()
    {
        return self::$api_key;
    }

    /**
     * @return string
     */
    public static function getWidgetScriptUrl()
    {
        if (getenv('COMFINO_DEV') && getenv('PS_DOMAIN') && getenv('COMFINO_DEV_WIDGET_SCRIPT_URL')
            && getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')
        ) {
            return getenv('COMFINO_DEV_WIDGET_SCRIPT_URL');
        }

        return self::$widget_script_url;
    }

    /**
     * @return string
     */
    public static function getPaywallFrontendScriptUrl()
    {
        if (getenv('COMFINO_DEV') && getenv('COMFINO_DEV_PAYWALL_FRONTEND_SCRIPT_URL')
            && getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')
        ) {
            return getenv('COMFINO_DEV_PAYWALL_FRONTEND_SCRIPT_URL');
        }

        return self::$paywall_frontend_script_url;
    }

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

    /**
     * @return string[]
     */
    public static function getHashAlgos()
    {
        return array_intersect(array_merge(['sha3-256'], PHP_VERSION_ID < 70100 ? ['sha512'] : []), hash_algos());
    }
}
