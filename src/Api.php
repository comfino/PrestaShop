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

require_once _PS_MODULE_DIR_ . 'comfino/src/ShopPluginErrorRequest.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ApiCache.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Order/Order.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Crypto/Sha3.php';

use Comfino\Api\CartInterface;
use Comfino\Crypto\Sha3;
use Comfino\ErrorLogger;
use Comfino\Order\Order;
use Comfino\ShopPluginErrorRequest;

class Api
{
    const COMFINO_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    const COMFINO_SANDBOX_HOST = 'https://api-ecommerce.craty.pl';

    const COMFINO_SDK_PRODUCTION_HOST = 'https://sdk.comfino.pl';
    const COMFINO_SDK_SANDBOX_HOST = 'https://sdk.craty.pl';

    /* Bounds for the synchronous outbound calls made while shop pages are being rendered. */
    const CONNECT_TIMEOUT = 3;
    const REQUEST_TIMEOUT = 10;

    const INSTALLMENTS_ZERO_PERCENT = 'INSTALLMENTS_ZERO_PERCENT';
    const CONVENIENT_INSTALLMENTS = 'CONVENIENT_INSTALLMENTS';
    const PAY_LATER = 'PAY_LATER';

    /** @var bool */
    private static $is_sandbox_mode;

    /** @var bool */
    private static $use_dev_env_vars = false;

    /** @var string */
    private static $api_host;

    /** @var string */
    private static $api_key;

    /** @var string */
    private static $widget_key;

    /** @var string|null */
    private static $last_request_body;

    /** @var string|null */
    private static $last_response_body;

    /** @var int|null */
    private static $last_response_code;

    /** @var array */
    private static $last_response_headers = [];

    /** @var array */
    private static $last_errors = [];

    /**
     * @var \PaymentModule
     */
    private static $module;

    public static function init($module)
    {
        self::$module = $module;

        $config_manager = new ConfigManager($module);

        self::$is_sandbox_mode = (bool) $config_manager->getConfigurationValue('COMFINO_IS_SANDBOX');
        self::$use_dev_env_vars = $config_manager->useDevEnvVars();
        self::$widget_key = $config_manager->getConfigurationValue('COMFINO_WIDGET_KEY');

        if (self::$is_sandbox_mode) {
            self::$api_host = self::COMFINO_SANDBOX_HOST;
            self::$api_key = $config_manager->getConfigurationValue('COMFINO_SANDBOX_API_KEY');
        } else {
            self::$api_host = self::COMFINO_PRODUCTION_HOST;
            self::$api_key = $config_manager->getConfigurationValue('COMFINO_API_KEY');
        }
    }

    /**
     * @return bool
     */
    public static function isSandboxMode()
    {
        return (bool) self::$is_sandbox_mode;
    }

    /**
     * @param Order $order
     *
     * @return array|bool
     */
    public static function createOrder(Order $order)
    {
        /* Redirects are not followed here on purpose: the endpoint is known and must not be able to replay the
           Api-Key header and the customer data of the request body to another host. */
        $response = self::sendRequest(
            self::getApiHost() . '/v1/orders',
            'POST',
            [],
            self::prepareCreateOrderRequestBody($order)
        );

        return $response !== false ? json_decode($response, true) : false;
    }

    /**
     * @param string $order_id
     *
     * @return void
     */
    public static function cancelOrder($order_id)
    {
        self::sendRequest(self::getApiHost() . "/v1/orders/$order_id/cancel", 'PUT');
    }

    /**
     * @return void
     */
    public static function notifyPluginRemoval()
    {
        if (!empty(self::getApiKey())) {
            self::sendRequest(self::getApiHost() . '/v1/log-plugin-remove', 'PUT');
        }
    }

    /**
     * @return string|bool
     */
    public static function getWidgetKey()
    {
        if (empty(self::getApiKey())) {
            return '';
        }

        $cache_key = self::cacheKey('widget_key');
        $cached_key = ApiCache::get($cache_key);

        if (is_string($cached_key)) {
            return $cached_key;
        }

        if (ApiCache::isCircuitOpen()) {
            $stale_key = ApiCache::getStale($cache_key);

            return is_string($stale_key) ? $stale_key : false;
        }

        $widget_key = self::sendRequest(self::getApiHost() . '/v1/widget-key', 'GET');

        if (count(self::$last_errors)) {
            ApiCache::recordFailure();

            $stale_key = ApiCache::getStale($cache_key);

            return is_string($stale_key) ? $stale_key : false;
        }

        ApiCache::recordSuccess();

        $widget_key = json_decode($widget_key, true);

        if (is_string($widget_key)) {
            ApiCache::set($cache_key, $widget_key, ApiCache::WIDGET_KEY_TTL);
        }

        return $widget_key;
    }

    /**
     * @param string $list_type
     * @param bool $use_public_names When true, returns customer-facing names (for the frontend SDK) instead of the
     *                               admin-facing names used in the plugin's own settings UI. The API may return either
     *                               a plain name string per product type code, or a [internalName, publicName] tuple -
     *                               both shapes are supported
     *
     * @return string[]|bool
     */
    public static function getProductTypes($list_type, $use_public_names = false)
    {
        static $product_types = [];

        if (!isset($product_types[$list_type])) {
            $names = self::fetchProductTypeNames($list_type);

            if ($names === false) {
                return false;
            }

            $product_types[$list_type] = $names;
        }

        return $product_types[$list_type][$use_public_names ? 'public' : 'internal'];
    }

    /**
     * Resolves the internal/public product type names for a list type, preferring the cache and never calling the
     * API while the circuit is open. A stale entry is served in preference to failing, because the alternative is
     * withdrawing the payment method over a transient upstream problem.
     *
     * @param string $list_type
     *
     * @return array|bool Array with the `internal` and `public` keys, or false when no data can be resolved.
     */
    private static function fetchProductTypeNames($list_type)
    {
        $cache_key = self::cacheKey('product_types_' . $list_type);
        $cached_names = ApiCache::get($cache_key);

        if (self::isProductTypeNames($cached_names)) {
            return $cached_names;
        }

        if (ApiCache::isCircuitOpen()) {
            $stale_names = ApiCache::getStale($cache_key);

            return self::isProductTypeNames($stale_names) ? $stale_names : false;
        }

        $prod_types = self::sendRequest(self::getApiHost() . '/v2/product-types?listType=' . $list_type, 'GET');

        if ($prod_types === false || count(self::$last_errors) || strpos($prod_types, 'errors') !== false) {
            ApiCache::recordFailure();

            $stale_names = ApiCache::getStale($cache_key);

            return self::isProductTypeNames($stale_names) ? $stale_names : false;
        }

        ApiCache::recordSuccess();

        $internal_names = [];
        $public_names = [];

        foreach ((array) json_decode($prod_types, true) as $product_type_code => $names) {
            if (is_array($names)) {
                $internal_names[$product_type_code] = isset($names[0]) ? $names[0] : '';
                $public_names[$product_type_code] = isset($names[1]) ? $names[1] : $internal_names[$product_type_code];
            } else {
                $internal_names[$product_type_code] = $names;
                $public_names[$product_type_code] = $names;
            }
        }

        if (!count($internal_names)) {
            // An empty list is not cached - it would silently disable the payment method for a full TTL.
            return false;
        }

        $product_type_names = ['internal' => $internal_names, 'public' => $public_names];

        ApiCache::set($cache_key, $product_type_names, ApiCache::PRODUCT_TYPES_TTL);

        return $product_type_names;
    }

    /**
     * @param mixed $names
     *
     * @return bool
     */
    private static function isProductTypeNames($names)
    {
        return is_array($names) && isset($names['internal'], $names['public']) && count($names['internal']);
    }

    /**
     * Cache keys are scoped by the environment and by a fingerprint of the API key in use. Both the available
     * financial products and the widget key belong to a specific merchant account, so data fetched with one set
     * of credentials must never be served after the credentials change - including within the same request,
     * where the admin settings form validates a freshly submitted key.
     *
     * The fingerprint is a truncated digest, never the key itself.
     *
     * @param string $name
     *
     * @return string
     */
    private static function cacheKey($name)
    {
        $api_key = (string) self::getApiKey();

        return $name .
            (self::$is_sandbox_mode ? '_sandbox_' : '_production_') .
            ($api_key !== '' ? substr(sha1($api_key), 0, 12) : 'nokey');
    }

    /**
     * @return string[]|bool
     */
    public static function getWidgetTypes()
    {
        static $product_types = null;

        if ($product_types === null) {
            $product_types = self::sendRequest(self::getApiHost() . '/widget/v1/widget-types', 'GET');

            if ($product_types !== false && !count(self::$last_errors) && strpos($product_types, 'errors') === false) {
                $product_types = json_decode($product_types, true);
            } else {
                $product_types = null;

                return false;
            }
        }

        return $product_types;
    }

    /**
     * @return bool
     */
    public static function isShopAccountActive()
    {
        $account_active = false;

        if (!empty(self::getApiKey())) {
            $response = self::sendRequest(self::getApiHost() . '/v1/user/is-active', 'GET', [], null, false);

            if (!count(self::$last_errors)) {
                $account_active = json_decode($response, true);
            }
        }

        return $account_active;
    }

    /**
     * @param ShopPluginError $error
     *
     * @return bool
     */
    public static function sendLoggedError(ShopPluginError $error)
    {
        $request = new ShopPluginErrorRequest();

        if (!$request->prepareRequest($error, self::getUserAgentHeader())) {
            ErrorLogger::logError('Error request preparation failed', $error->errorMessage);

            return false;
        }

        $data = ['error_details' => $request->errorDetails, 'hash' => $request->hash];
        $response = self::sendRequest(self::getApiHost() . '/v1/log-plugin-error', 'POST', [], $data, false);

        return strpos($response, 'errors') === false;
    }

    /**
     * Claims a short-lived access token which the frontend SDK uses to report browser-side errors.
     *
     * @return array|bool ['access_token' => string, 'expires_at' => string] or false on failure
     */
    public static function claimErrorLoggingToken()
    {
        if (empty(self::getApiKey())) {
            return false;
        }

        $response = self::sendRequest(self::getApiHost() . '/v1/error-logging-token', 'POST', [], null, false);

        if ($response === false || count(self::$last_errors)) {
            return false;
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !isset($decoded['access_token'], $decoded['expires_at'])) {
            return false;
        }

        return $decoded;
    }

    /**
     * Fetches remote feature-flag attributes. Called only when the flag list carried by the
     * "Comfino-Flags" header on the error-logging-token response has changed, since attributes are
     * never modified independently of their flag.
     *
     * @return array|bool assoc array [flagName => attributes] or false on failure
     */
    public static function getUserSettingsFlags()
    {
        if (empty(self::getApiKey())) {
            return false;
        }

        $response = self::sendRequest(self::getApiHost() . '/v1/user/settings/flags', 'GET', [], null, false);

        if ($response === false || count(self::$last_errors)) {
            return false;
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !isset($decoded['flags']) || !is_array($decoded['flags'])) {
            return false;
        }

        return $decoded['flags'];
    }

    /**
     * Fire-and-forget shop environment report. Failures are never propagated to the caller.
     *
     * @param array $report
     *
     * @return bool
     */
    public static function reportShopEnvironment(array $report)
    {
        if (empty(self::getApiKey())) {
            return false;
        }

        $response = self::sendRequest(
            self::getApiHost() . '/v1/log-shop-environment',
            'POST',
            [],
            $report,
            false
        );

        return $response !== false && !count(self::$last_errors);
    }

    /**
     * @return bool
     */
    public static function isApiKeyValid()
    {
        $response = self::sendRequest(self::getApiHost() . '/v1/user/is-active', 'GET', [], null, false);

        return strpos($response, 'errors') === false;
    }

    /**
     * @return string
     */
    public static function getLogoUrl()
    {
        return self::getApiHost() . '/v1/get-logo-url?auth=' . self::getLogoAuthHash();
    }

    /**
     * @return string
     */
    public static function getPaywallLogoUrl()
    {
        return self::getApiHost() . '/v1/get-paywall-logo?auth=' . self::getLogoAuthHash(true);
    }

    /**
     * @param bool $is_sandbox_mode
     *
     * @return void
     */
    public static function setSandboxMode($is_sandbox_mode)
    {
        self::$is_sandbox_mode = $is_sandbox_mode;
    }

    /**
     * @param string $api_host
     *
     * @return void
     */
    public static function setApiHost($api_host)
    {
        self::$api_host = $api_host;
    }

    /**
     * @param string $api_key
     *
     * @return void
     */
    public static function setApiKey($api_key)
    {
        self::$api_key = $api_key;
    }

    /**
     * Lets the config save handler (comfino.php) refresh the dev-env-vars gate right after persisting a change
     * to COMFINO_DEV_ENV_VARS, so the very same request's API key validation call already honors it instead of
     * requiring a second save.
     *
     * @param bool $use_dev_env_vars
     *
     * @return void
     */
    public static function setUseDevEnvVars($use_dev_env_vars)
    {
        self::$use_dev_env_vars = $use_dev_env_vars;
    }

    /**
     * @return string|null
     */
    public static function getLastRequestBody()
    {
        return self::$last_request_body;
    }

    /**
     * @return string|null
     */
    public static function getLastResponseBody()
    {
        return self::$last_response_body;
    }

    /**
     * @param string $name
     * @param string $default
     *
     * @return string
     */
    public static function getLastResponseHeader($name, $default = '')
    {
        $name = \Tools::strtolower($name);

        return isset(self::$last_response_headers[$name]) ? self::$last_response_headers[$name] : $default;
    }

    /**
     * @return int|null
     */
    public static function getLastResponseCode()
    {
        return self::$last_response_code;
    }

    /**
     * @return array
     */
    public static function getLastErrors()
    {
        return self::$last_errors;
    }

    /**
     * @return string
     */
    public static function getApiKey()
    {
        return self::$api_key;
    }

    /**
     * Resolves the base URL of the frontend SDK CDN (checkout glue, ESM SDK, product widget bridge,
     * checkout CSS). A local-development override may be supplied via the COMFINO_DEV_SDK_CDN_BASE_URL
     * environment variable (only honoured in dev mode); it always points at the SDK CDN host, never at
     * generic static resources.
     *
     * @return string base URL without a trailing slash
     */
    public static function getSdkCdnBaseUrl()
    {
        $devOverride = self::getSdkCdnDevOverride();

        if ($devOverride !== null) {
            return $devOverride;
        }

        return self::$is_sandbox_mode ? self::COMFINO_SDK_SANDBOX_HOST : self::COMFINO_SDK_PRODUCTION_HOST;
    }

    /**
     * Checkout glue script that dynamically imports the ESM SDK into the paywall container.
     *
     * @return string
     */
    public static function getCheckoutScriptUrl()
    {
        return self::getSdkCdnBaseUrl() . '/checkout/v1/' . self::sdkScriptFileName('comfino-prestashop');
    }

    /**
     * ESM SDK bundle imported by the checkout glue and the product widget bridge; also passed to the
     * frontend in the config blob as "sdkScriptUrl".
     *
     * @return string
     */
    public static function getSdkScriptUrl()
    {
        return self::getSdkCdnBaseUrl() . '/sdk/v1/' . self::sdkScriptFileName('comfino-sdk');
    }

    /**
     * Product-page widget bridge script (self-bootstraps and imports the same ESM SDK).
     *
     * @return string
     */
    public static function getProductWidgetScriptUrl()
    {
        return self::getSdkCdnBaseUrl() . '/product/v1/' . self::sdkScriptFileName('comfino-prestashop-widget');
    }

    /**
     * Optional checkout item-gate stylesheet served from the SDK CDN.
     *
     * @return string
     */
    public static function getCheckoutStyleUrl()
    {
        return self::getSdkCdnBaseUrl() . '/checkout/v1/css/comfino-item-gate-prestashop.css';
    }

    /**
     * Returns the dev-mode SDK CDN base URL override (without a trailing slash), or null when it is
     * not applicable. Gated by ConfigManager::useDevEnvVars() (COMFINO_DEV_ENV + the admin-controlled
     * COMFINO_DEV_ENV_VARS option), matching the other COMFINO_DEV_* overrides in this class.
     *
     * @return string|null
     */
    private static function getSdkCdnDevOverride()
    {
        if (self::$use_dev_env_vars && getenv('COMFINO_DEV_SDK_CDN_BASE_URL')) {
            return rtrim(getenv('COMFINO_DEV_SDK_CDN_BASE_URL'), '/');
        }

        return null;
    }

    /**
     * Builds an SDK script file name; serves the unminified variant when dev env vars are active and
     * COMFINO_DEV_USE_UNMINIFIED_SCRIPTS is set.
     *
     * @param string $baseName
     *
     * @return string
     */
    private static function sdkScriptFileName($baseName)
    {
        $useUnminified = self::$use_dev_env_vars && ConfigManager::useUnminifiedScripts();

        return $baseName . ($useUnminified ? '.js' : '.min.js');
    }

    /**
     * Logo displayed on the payment method tile before the SDK replaces it with the authorized one.
     *
     * @return string
     */
    public static function getDefaultLogoUrl()
    {
        return self::getSdkCdnBaseUrl() . '/images/comfino/comfino_logo.svg';
    }

    /**
     * @param string|null $api_host
     *
     * @return string
     */
    public static function getApiHost($api_host = null)
    {
        if (self::$use_dev_env_vars && getenv('COMFINO_DEV_API_HOST')) {
            return getenv('COMFINO_DEV_API_HOST');
        }

        return $api_host !== null ? $api_host : self::$api_host;
    }

    /**
     * SHA3-256 is always available: natively on PHP >= 7.1, via the pure-PHP fallback
     * (Comfino\Crypto\Sha3) below that.
     *
     * @return string[]
     */
    public static function getHashAlgos()
    {
        return ['sha3-256'];
    }

    /**
     * SHA3-256 of the given string, using the native implementation when available (PHP 7.1+) and the
     * pure-PHP fallback otherwise (PHP 5.6/7.0). Both produce byte-identical output.
     *
     * @param string $data
     *
     * @return string 64 lowercase hex chars
     */
    public static function hashSha3256($data)
    {
        if (in_array('sha3-256', hash_algos(), true)) {
            return hash('sha3-256', $data);
        }

        return Sha3::hash256($data);
    }

    /**
     * @param array $product
     *
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
     * @param string $url
     * @param string $request_type
     * @param array $extra_options
     * @param string $data
     * @param bool $log_errors
     *
     * @return string|bool
     */
    private static function sendRequest($url, $request_type, $extra_options = [], $data = null, $log_errors = true)
    {
        self::$last_request_body = null;
        self::$last_response_body = null;
        self::$last_response_code = null;
        self::$last_response_headers = [];
        self::$last_errors = [];

        $method = \Tools::strtoupper($request_type);

        // The development environment may be served over plain HTTP; production traffic never is.
        $allowed_protocols = self::$use_dev_env_vars
            ? CURLPROTO_HTTP | CURLPROTO_HTTPS
            : CURLPROTO_HTTPS;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => self::getRequestHeaders($method, $data),
            CURLOPT_RETURNTRANSFER => true,
            /* An unresponsive upstream must never be able to hold a PHP worker open indefinitely - these calls
               are made synchronously while front office pages are being rendered. */
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            // Set explicitly so that a permissive php.ini cannot weaken the TLS verification.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => $allowed_protocols,
            CURLOPT_REDIR_PROTOCOLS => $allowed_protocols,
            CURLOPT_HEADERFUNCTION => static function ($curl_handle, $header_line) {
                $separator_pos = strpos($header_line, ':');

                if ($separator_pos !== false) {
                    $header_name = \Tools::strtolower(trim(substr($header_line, 0, $separator_pos)));
                    self::$last_response_headers[$header_name] = trim(substr($header_line, $separator_pos + 1));
                }

                return strlen($header_line);
            },
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
     *
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
                    "Communication error [$error_id]",
                    curl_errno($curl),
                    curl_error($curl),
                    self::$last_response_code,
                    $url,
                    $data !== null ? json_encode($data) : null,
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
                        'Payment error',
                        0,
                        implode(', ', $errors),
                        self::$last_response_code,
                        $url,
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
                        "Payment error [$error_id]",
                        self::$last_response_code,
                        'API error.',
                        self::$last_response_code,
                        $url,
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
     *
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
        // The API key must never leave the module in a log message, local or remote.
        return implode(
            ', ',
            array_map(
                static function ($header) {
                    return preg_replace('/^(Api-Key:\s*).*$/i', '$1[REDACTED]', $header);
                },
                $headers
            )
        );
    }

    /**
     * @param string $method
     *
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
    private static function getUserAgentHeader()
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
     *
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
            $authHash .= self::$widget_key;
            $authHash .= in_array('sha3-256', hash_algos(), true)
                ? hash_hmac('sha3-256', $authHash, self::getApiKey(), true)
                : Sha3::hmac256($authHash, self::getApiKey(), true);
        }

        return urlencode(base64_encode($authHash));
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    private static function prepareCreateOrderRequestBody(Order $order)
    {
        $customer = $order->getCustomer();

        return array_filter(
            [
                // Basic order data
                'notifyUrl' => $order->getNotifyUrl(),
                'returnUrl' => $order->getReturnUrl(),
                'orderId' => $order->getId(),

                // Payment data
                'loanParameters' => array_filter(
                    [
                        'amount' => $order->getLoanParameters()->getAmount(),
                        'term' => $order->getLoanParameters()->getTerm(),
                        'type' => $order->getLoanParameters()->getType(),
                        'allowedProductTypes' => $order->getLoanParameters()->getAllowedProductTypes(),
                    ],
                    static function ($value) {
                        return $value !== null;
                    }
                ),

                // Cart with list of products
                'cart' => self::getCartAsArray($order->getCart()),

                // Customer data (mandatory)
                'customer' => array_filter(
                    [
                        'firstName' => $customer->getFirstName(),
                        'lastName' => $customer->getLastName(),
                        'email' => $customer->getEmail(),
                        'phoneNumber' => $customer->getPhoneNumber(),
                        'taxId' => $customer->getTaxId(),
                        'ip' => $customer->getIp(),
                        'regular' => $customer->isRegular(),
                        'logged' => $customer->isLogged(),

                        // Customer address (optional)
                        'address' => count(
                            $address = array_filter(
                                [
                                    'street' => ($nullsafeVariable1 = $customer->getAddress())
                                        ? $nullsafeVariable1->getStreet() : null,
                                    'buildingNumber' => ($nullsafeVariable2 = $customer->getAddress())
                                        ? $nullsafeVariable2->getBuildingNumber() : null,
                                    'apartmentNumber' => ($nullsafeVariable3 = $customer->getAddress())
                                        ? $nullsafeVariable3->getApartmentNumber() : null,
                                    'postalCode' => ($nullsafeVariable4 = $customer->getAddress())
                                        ? $nullsafeVariable4->getPostalCode() : null,
                                    'city' => ($nullsafeVariable5 = $customer->getAddress())
                                        ? $nullsafeVariable5->getCity() : null,
                                    'countryCode' => ($nullsafeVariable6 = $customer->getAddress())
                                        ? $nullsafeVariable6->getCountryCode() : null,
                                ],
                                static function ($value) {
                                    return $value !== null;
                                }
                            )
                        ) ? $address : null,
                    ],
                    static function ($value) {
                        return $value !== null;
                    }
                ),

                // Seller data (optional)
                'seller' => count(
                    $seller = array_filter(
                        ['taxId' => ($nullsafeVariable7 = $order->getSeller()) ? $nullsafeVariable7->getTaxId() : null],
                        static function ($value) {
                            return $value !== null;
                        }
                    )
                ) ? $seller : null,

                // Extra data (optional)
                'accountNumber' => $order->getAccountNumber(),
                'transferTitle' => $order->getTransferTitle(),
            ],
            static function ($value) {
                return $value !== null;
            }
        );
    }

    /**
     * Serializes a cart to the array shape accepted by the v3 API and by the frontend SDK bootstrap config.
     *
     * @param CartInterface $cart
     *
     * @return array
     */
    public static function getCartAsArray(CartInterface $cart)
    {
        $products = [];
        $cartTotal = 0;

        foreach ($cart->getItems() as $cartItem) {
            $products[] = array_filter(
                [
                    'name' => $cartItem->getProduct()->getName(),
                    'quantity' => $cartItem->getQuantity(),
                    'price' => $cartItem->getProduct()->getPrice(),
                    'photoUrl' => $cartItem->getProduct()->getPhotoUrl(),
                    'ean' => $cartItem->getProduct()->getEan(),
                    'externalId' => $cartItem->getProduct()->getId(),
                    'category' => $cartItem->getProduct()->getCategory(),
                    'netPrice' => $cartItem->getProduct()->getNetPrice(),
                    'vatRate' => $cartItem->getProduct()->getTaxRate(),
                    'vatAmount' => $cartItem->getProduct()->getTaxValue(),
                ],
                static function ($value) {
                    return $value !== null;
                }
            );

            $cartTotal += ($cartItem->getProduct()->getPrice() * $cartItem->getQuantity());
        }

        $cartTotalWithDelivery = $cartTotal + ($cart->getDeliveryCost() !== null ? $cart->getDeliveryCost() : 0);
        $cartTotalItemsSumDifference = (int) ($cart->getTotalAmount() - $cartTotalWithDelivery);

        if ($cartTotalWithDelivery > $cart->getTotalAmount()) {
            // Add discount item to the list - problems with cart items value and order total value inconsistency.
            $products[] = [
                'name' => 'Rabat',
                'quantity' => 1,
                'price' => $cartTotalItemsSumDifference,
                'netPrice' => $cartTotalItemsSumDifference,
                'vatRate' => null,
                'vatAmount' => 0,
                'category' => 'DISCOUNT',
            ];
        } elseif ($cartTotalWithDelivery < $cart->getTotalAmount()) {
            // Add correction item to the list - problems with cart items value and order total value inconsistency.
            $products[] = [
                'name' => 'Korekta',
                'quantity' => 1,
                'price' => $cartTotalItemsSumDifference,
                'netPrice' => $cartTotalItemsSumDifference,
                'vatRate' => null,
                'vatAmount' => 0,
                'category' => 'ADDITIONAL_FEE',
            ];
        }

        return array_filter(
            [
                'products' => $products,
                'totalAmount' => $cart->getTotalAmount(),
                'deliveryCost' => $cart->getDeliveryCost(),
                'deliveryNetCost' => $cart->getDeliveryNetCost(),
                'deliveryCostVatRate' => $cart->getDeliveryCostTaxRate(),
                'deliveryCostVatAmount' => $cart->getDeliveryCostTaxValue(),
                'category' => $cart->getCategory(),
            ],
            static function ($value) {
                return $value !== null;
            }
        );
    }
}
