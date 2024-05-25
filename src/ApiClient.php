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

    /** @var string|null */
    private static $last_request_body;

    /** @var string|null */
    private static $last_response_body;

    /** @var int|null */
    private static $last_response_code;

    /** @var array */
    private static $last_errors = [];

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

            $api_host = null;

            if (getenv('COMFINO_DEV') && getenv('PS_DOMAIN') && getenv('COMFINO_DEV_API_HOST_BACKEND')
                && getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')
            ) {
                $api_host = getenv('COMFINO_DEV_API_HOST_BACKEND');
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
                $api_host,
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

    //-----------------------------------------

    /**
     * @param string $list_type
     * @return string[]|bool
     */
    public static function getProductTypes($list_type)
    {
        static $product_types = [];

        if (!isset($product_types[$list_type])) {
            $prod_types = self::sendRequest(self::getApiHost() . '/v1/product-types?listType=' . $list_type, 'GET');

            if ($prod_types !== false && !count(self::$last_errors) && strpos($prod_types, 'errors') === false) {
                $prod_types = json_decode($prod_types, true);
            } else {
                return false;
            }

            $product_types[$list_type] = $prod_types;
        }

        return $product_types[$list_type];
    }

    public static function getLogoUrl(): string
    {
        return self::getApiHost(true) . '/v1/get-logo-url?auth=' . self::getLogoAuthHash();
    }

    public static function getPaywallLogoUrl(): string
    {
        return self::getApiHost(true) . '/v1/get-paywall-logo?auth=' . self::getLogoAuthHash(true);
    }

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
     * @param bool $frontend_host
     * @param string|null $api_host
     * @return string
     */
    public static function getApiHost($frontend_host = false, $api_host = null)
    {
        if (getenv('COMFINO_DEV') && getenv('PS_DOMAIN')
            && getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')
        ) {
            if ($frontend_host) {
                if (getenv('COMFINO_DEV_API_HOST_FRONTEND')) {
                    return getenv('COMFINO_DEV_API_HOST_FRONTEND');
                }
            } elseif (getenv('COMFINO_DEV_API_HOST_BACKEND')) {
                return getenv('COMFINO_DEV_API_HOST_BACKEND');
            }
        }

        return $api_host ?? self::$api_host;
    }

    /**
     * @return string
     */
    public static function getPaywallApiHost()
    {
        if (getenv('COMFINO_DEV') && getenv('PS_DOMAIN')
            && getenv('COMFINO_DEV_API_PAYWALL_HOST')
            && getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')
        ) {
            return getenv('COMFINO_DEV_API_PAYWALL_HOST');
        }

        return self::$api_paywall_host;
    }

    /**
     * @return string[]
     */
    public static function getHashAlgos()
    {
        return array_intersect(array_merge(['sha3-256'], PHP_VERSION_ID < 70100 ? ['sha512'] : []), hash_algos());
    }

    /**
     * @param array $product
     * @return string
     */
    private static function getProductsImageUrl($product)
    {
        $link_rewrite = '';

        if (is_array($product['link_rewrite'])) {
            foreach ($product['link_rewrite'] as $link) {
                $link_rewrite = $link;
            }
        } else {
            $link_rewrite = $product['link_rewrite'];
        }

        $image = \Image::getCover($product['id_product']);

        if (!is_array($image) && !isset($image['id_image'])) {
            return '';
        }

        $imageUrl = (new \Link())->getImageLink($link_rewrite, $image['id_image']);

        if (strpos($imageUrl, 'http') === false) {
            $imageUrl = 'https://' . $imageUrl;
        }

        return $imageUrl;
    }

    /**
     * @param \Context $context
     * @return string
     */
    private static function getNotifyUrl($context)
    {
        return $context->link->getModuleLink($context->controller->module->name, 'notify', [], true);
    }

    /**
     * @param string $return_url
     * @return string
     */
    private static function getReturnUrl($return_url)
    {
        return \Tools::getHttpHost(true) . __PS_BASE_URI__ . $return_url;
    }

    /**
     * @param string $url
     * @param string $request_type
     * @param array $extra_options
     * @param string $data
     * @param bool $log_errors
     * @return string|bool
     */
    private static function sendRequest($url, $request_type, $extra_options = [], $data = null, $log_errors = true)
    {
        self::$last_request_body = null;
        self::$last_response_body = null;
        self::$last_response_code = null;
        self::$last_errors = [];

        $method = \Tools::strtoupper($request_type);

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => self::getRequestHeaders($method, $data),
            CURLOPT_RETURNTRANSFER => true,
        ];

        switch ($options[CURLOPT_CUSTOMREQUEST]) {
            case 'POST':
            case 'PUT':
                if ($data !== null) {
                    self::$last_request_body = json_encode($data);
                    $options[CURLOPT_POSTFIELDS] = self::$last_request_body;
                }

                break;
        }

        $curl = curl_init();
        curl_setopt_array($curl, $options + $extra_options);

        $response = self::processResponse($curl, $url, $data, $log_errors, $options[CURLOPT_HTTPHEADER]);

        curl_close($curl);

        self::$last_response_body = $response;

        return $response;
    }

    /**
     * @param resource $curl
     * @param string $url
     * @param mixed $data
     * @param bool $log_errors
     * @param array $headers
     * @return string|bool
     */
    private static function processResponse($curl, $url, $data, $log_errors, $headers)
    {
        $response = curl_exec($curl);

        self::$last_response_code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($response === false || self::$last_response_code > 400) {
            $error_id = time();

            self::$last_errors = [
                "Communication error: $error_id. Please contact with support and note this error id.",
            ];

            if ($log_errors) {
                ErrorLogger::sendError(
                    "Communication error [$error_id]", curl_errno($curl), curl_error($curl),
                    $url, $data !== null ? json_encode($data) : null,
                    !empty($response) ? $response : self::$last_response_code
                );
            }

            $response = json_encode(['errors' => self::$last_errors]);
        } else {
            $decoded = json_decode($response, true);

            if ($decoded !== false && (isset($decoded['errors']) || isset($decoded['message']))) {
                $errors = [];

                if (isset($decoded['errors'])) {
                    $errors = array_map(
                        static function ($k, $v) { return "$k: $v"; },
                        array_keys($decoded['errors']),
                        array_values($decoded['errors'])
                    );
                } elseif (isset($decoded['message'])) {
                    $errors[] = $decoded['message'];
                }

                if ($log_errors) {
                    ErrorLogger::sendError(
                        'Payment error', 0, implode(', ', $errors), $url,
                        self::getApiRequestForLog($headers, $data !== null ? json_encode($data) : null),
                        $response
                    );
                }

                self::$last_errors = $errors;

                $response = json_encode(['errors' => self::$last_errors]);
            } elseif (self::$last_response_code >= 400) {
                $error_id = time();

                if ($log_errors) {
                    ErrorLogger::sendError(
                        "Payment error [$error_id]", self::$last_response_code, 'API error.', $url,
                        self::getApiRequestForLog($headers, $data !== null ? json_encode($data) : null),
                        $response
                    );
                }

                self::$last_errors = ["Payment error: $error_id. Please contact with support and note this error id."];

                $response = json_encode(['errors' => self::$last_errors]);
            }
        }

        if ($response !== false) {
            self::$last_response_body = $response;
        }

        return $response;
    }

    /**
     * @param array $headers
     * @param string $body
     * @return string
     */
    private static function getApiRequestForLog(array $headers, $body)
    {
        return 'Headers: ' . self::getHeadersForLog($headers) . "\nBody: " . ($body !== null ? $body : 'n/a');
    }

    /**
     * @return string
     */
    private static function getHeadersForLog(array $headers)
    {
        return implode(', ', $headers);
    }

    /**
     * @param string $method
     * @return array
     */
    private static function getRequestHeaders($method = 'GET', $data = null)
    {
        $headers = [];

        if (($method === 'POST' || $method === 'PUT') && $data !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        return array_merge($headers, [
            'Api-Key: ' . self::getApiKey(),
            'Api-Language: ' . \Context::getContext()->language->iso_code,
            'User-Agent: ' . self::getUserAgentHeader(),
        ]);
    }

    /**
     * @return string
     */
    public static function getUserAgentHeader()
    {
        return sprintf(
            'PS Comfino [%s], PS [%s], SF [%s], PHP [%s], %s',
            COMFINO_VERSION,
            _PS_VERSION_,
            COMFINO_PS_17 && class_exists('\Symfony\Component\HttpKernel\Kernel')
                ? \Symfony\Component\HttpKernel\Kernel::VERSION
                : 'n/a',
            PHP_VERSION,
            \Tools::getShopDomain()
        );
    }

    /**
     * @param bool $paywallLogo
     * @return string
     */
    private static function getLogoAuthHash($paywallLogo = false)
    {
        $platformVersion = array_map('intval', explode('.', _PS_VERSION_));
        $pluginVersion = array_map('intval', explode('.', COMFINO_VERSION));
        $packedPlatformVersion = pack('c*', ...$platformVersion);
        $packedPluginVersion = pack('c*', ...$pluginVersion);
        $platformVersionLength = pack('c', strlen($packedPlatformVersion));
        $pluginVersionLength = pack('c', strlen($packedPluginVersion));

        $authHash = "PS$platformVersionLength$pluginVersionLength$packedPlatformVersion$packedPluginVersion";

        if ($paywallLogo) {
            $hashAlgorithm = current(self::getHashAlgos());
            $authHash .= self::$widget_key;
            $authHash .= hash_hmac($hashAlgorithm, $authHash, self::getApiKey(), true);
        }

        return urlencode(base64_encode($authHash));
    }
}
