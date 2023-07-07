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

require_once 'ShopPluginErrorRequest.php';
require_once 'ErrorLogger.php';

class Api
{
    const COMFINO_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    const COMFINO_SANDBOX_HOST = 'https://api-ecommerce.ecraty.pl';
    const WIDGET_SCRIPT_PRODUCTION_URL = '//widget.comfino.pl/comfino.min.js';
    const WIDGET_SCRIPT_SANDBOX_URL = '//widget.craty.pl/comfino.min.js';

    const INSTALLMENTS_ZERO_PERCENT = 'INSTALLMENTS_ZERO_PERCENT';
    const CONVENIENT_INSTALLMENTS = 'CONVENIENT_INSTALLMENTS';
    const PAY_LATER = 'PAY_LATER';

    /** @var bool */
    private static $is_sandbox_mode;

    /** @var string */
    private static $api_host;

    /** @var string */
    private static $api_key;

    /** @var string */
    private static $widget_script_url;

    /** @var string|null */
    private static $last_request_body;

    /** @var string|null */
    private static $last_response_body;

    /** @var int|null */
    private static $last_response_code;

    /** @var array */
    private static $last_errors = [];

    public static function init()
    {
        $config_manager = new ConfigManager();

        $sandbox_key = $config_manager->getConfigurationValue('COMFINO_SANDBOX_API_KEY');
        $production_key = $config_manager->getConfigurationValue('COMFINO_API_KEY');

        self::$is_sandbox_mode = (bool) $config_manager->getConfigurationValue('COMFINO_IS_SANDBOX');
        self::$api_host = self::$is_sandbox_mode ? self::COMFINO_SANDBOX_HOST : self::COMFINO_PRODUCTION_HOST;
        self::$api_key = self::$is_sandbox_mode ? $sandbox_key : $production_key;
        self::$widget_script_url = self::$is_sandbox_mode
            ? self::WIDGET_SCRIPT_SANDBOX_URL
            : self::WIDGET_SCRIPT_PRODUCTION_URL;
    }

    /**
     * @param \Cart $cart
     * @param string $order_id
     * @param string $return_url
     * @return array|bool
     */
    public static function createOrder($cart, $order_id, $return_url)
    {
        $total = (int) ($cart->getOrderTotal(true) * 100);
        $delivery = (int) ($cart->getOrderTotal(true, \Cart::ONLY_SHIPPING) * 100);

        $customer = new \Customer($cart->id_customer);
        $products = [];

        $cart_total = 0;

        foreach ($cart->getProducts() as $product) {
            $quantity = (int) $product['cart_quantity'];
            $price = (int) ($product['total_wt'] / $quantity * 100);

            $products[] = [
                'name' => $product['name'],
                'quantity' => $quantity,
                'price' => $price,
                'photoUrl' => self::getProductsImageUrl($product),
                'ean' => $product['ean13'],
                'externalId' => (string) $product['id_product'],
                'category' => $product['category'],
            ];

            $cart_total += ($price * $quantity);
        }

        $cart_total_with_delivery = $cart_total + $delivery;

        if ($cart_total_with_delivery > $total) {
            // Add discount item to the list - problems with cart items value and order total value inconsistency.
            $products[] = [
                'name' => 'Rabat',
                'quantity' => 1,
                'price' => (int) ($total - $cart_total_with_delivery),
                'photoUrl' => '',
                'ean' => '',
                'externalId' => '',
                'category' => 'DISCOUNT',
            ];
        } elseif ($cart_total_with_delivery < $total) {
            // Add correction item to the list - problems with cart items value and order total value inconsistency.
            $products[] = [
                'name' => 'Korekta',
                'quantity' => 1,
                'price' => (int) ($total - $cart_total_with_delivery),
                'photoUrl' => '',
                'ean' => '',
                'externalId' => '',
                'category' => 'CORRECTION',
            ];
        }

        $address = $cart->getAddressCollection();
        $address_explode = explode(' ', $address[$cart->id_address_delivery]->address1);
        $building_number = '';

        if (count($address_explode) === 2) {
            $building_number = $address_explode[1];
        }

        $context = \Context::getContext();
        $customer_tax_id = trim(str_replace('-', '', $address[$cart->id_address_delivery]->vat_number));
        $phone_number = trim($address[$cart->id_address_delivery]->phone);

        if (empty($phone_number)) {
            $phone_number = trim($address[$cart->id_address_delivery]->phone_mobile);
        }

        $data = [
            'notifyUrl' => self::getNotifyUrl($context),
            'returnUrl' => self::getReturnUrl($return_url),
            'orderId' => (string) $order_id,
            'draft' => false,
            'loanParameters' => [
                'term' => (int) $context->cookie->loan_term,
                'type' => $context->cookie->loan_type,
            ],
            'cart' => [
                'category' => 'Kategoria',
                'totalAmount' => $total,
                'deliveryCost' => $delivery,
                'products' => $products,
            ],
            'customer' => [
                'firstName' => $address[$cart->id_address_delivery]->firstname,
                'lastName' => $address[$cart->id_address_delivery]->lastname,
                'email' => $customer->email,
                'phoneNumber' => $phone_number,
                'ip' => \Tools::getRemoteAddr(),
                'regular' => !$customer->is_guest,
                'logged' => $customer->isLogged(),
                'address' => [
                    'street' => $address_explode[0],
                    'buildingNumber' => $building_number,
                    'apartmentNumber' => '',
                    'postalCode' => $address[$cart->id_address_delivery]->postcode,
                    'city' => $address[$cart->id_address_delivery]->city,
                    'countryCode' => 'PL',
                ],
            ],
        ];

        if (preg_match('/^[A-Z]{0,3}\d{7,}$/', $customer_tax_id)) {
            $data['customer']['taxId'] = $customer_tax_id;
        }

        $response = self::sendRequest(
            self::getApiHost() . '/v1/orders',
            'POST',
            [CURLOPT_FOLLOWLOCATION => true],
            $data
        );

        return $response !== false ? json_decode($response, true) : false;
    }

    /**
     * @param $loan_amount
     * @return array|bool
     */
    public static function getOffers($loan_amount)
    {
        $loan_amount = (float) $loan_amount;
        $response = self::sendRequest(self::getApiHost() . "/v1/financial-products?loanAmount=$loan_amount", 'GET');

        return $response !== false ? json_decode($response, true) : false;
    }

    /**
     * @param $self_link
     * @return array|bool
     */
    public static function getOrder($self_link)
    {
        $response = self::sendRequest(str_replace('https', 'http', $self_link), 'GET');

        return $response !== false ? json_decode($response, true) : false;
    }

    /**
     * @param string $order_id
     * @return void
     */
    public static function cancelOrder($order_id)
    {
        self::sendRequest(self::getApiHost() . "/v1/orders/$order_id/cancel", 'PUT');
    }

    /**
     * @return string|bool
     */
    public static function getWidgetKey()
    {
        $widget_key = '';

        if (!empty(self::getApiKey())) {
            $widget_key = self::sendRequest(self::getApiHost() . '/v1/widget-key', 'GET');

            if (!count(self::$last_errors)) {
                $widget_key = json_decode($widget_key, true);
            } else {
                $widget_key = false;
            }
        }

        return $widget_key;
    }

    /**
     * @param string $name
     * @param string $url
     * @param string $contactName
     * @param string $email
     * @param string $phone
     * @param array $agreements
     * @return array|bool
     */
    public static function registerShopAccount($name, $url, $contactName, $email, $phone, $agreements)
    {
        $data = [
            'name' => $name,
            'webSiteUrl' => $url,
            'contactName' => $contactName,
            'contactEmail' => $email,
            'contactPhone' => $phone,
            'platformId' => 11,
            'agreements' => $agreements,
        ];

        $response = self::sendRequest(self::getApiHost() . '/v1/user', 'POST', $data);

        return !count(self::$last_errors) ? json_decode($response, true) : false;
    }

    /**
     * @return array|bool
     */
    public static function getShopAccountAgreements()
    {
        $response = self::sendRequest(self::getApiHost() . '/v1/fetch-agreements', 'GET');

        return !count(self::$last_errors) ? json_decode($response, true) : false;
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
        return self::getApiHost(true) . '/v1/get-logo-url';
    }

    /**
     * @param bool $is_sandbox_mode
     * @return void
     */
    public static function setSandboxMode($is_sandbox_mode)
    {
        self::$is_sandbox_mode = $is_sandbox_mode;
    }

    /**
     * @param string $api_host
     * @return void
     */
    public static function setApiHost($api_host)
    {
        self::$api_host = $api_host;
    }

    /**
     * @param string $api_key
     * @return void
     */
    public static function setApiKey($api_key)
    {
        self::$api_key = $api_key;
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
     * @return string
     */
    public static function getWidgetScriptUrl()
    {
        if (getenv('COMFINO_DEV') && getenv('PS_DOMAIN') &&
            getenv('COMFINO_DEV_WIDGET_SCRIPT_URL') &&
            getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')
        ) {
            return getenv('COMFINO_DEV_WIDGET_SCRIPT_URL');
        }

        return self::$widget_script_url;
    }

    /**
     * @param bool $frontendHost
     * @return string
     */
    private static function getApiHost($frontendHost = false)
    {
        if (getenv('COMFINO_DEV') && getenv('PS_DOMAIN') &&
            getenv('COMFINO_DEV') === 'PS_' . _PS_VERSION_ . '_' . getenv('PS_DOMAIN')
        ) {
            if ($frontendHost) {
                if (getenv('COMFINO_DEV_API_HOST_FRONTEND')) {
                    return getenv('COMFINO_DEV_API_HOST_FRONTEND');
                }
            } else {
                if (getenv('COMFINO_DEV_API_HOST_BACKEND')) {
                    return getenv('COMFINO_DEV_API_HOST_BACKEND');
                }
            }
        }

        return self::$api_host;
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

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => \Tools::strtoupper($request_type),
            CURLOPT_HTTPHEADER => [
                'API-KEY: ' . self::getApiKey(),
                'User-Agent: ' . self::getUserAgentHeader(),
            ],
            CURLOPT_RETURNTRANSFER => true,
        ];

        switch ($options[CURLOPT_CUSTOMREQUEST]) {
            case 'POST':
            case 'PUT':
                if ($data !== null) {
                    self::$last_request_body = json_encode($data);

                    $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
                    $options[CURLOPT_POSTFIELDS] = self::$last_request_body;
                }

                break;
        }

        $curl = curl_init();
        curl_setopt_array($curl, $options + $extra_options);

        $response = self::processResponse($curl, $url, $data, $log_errors);

        curl_close($curl);

        self::$last_response_body = $response;

        return $response;
    }

    /**
     * @param resource $curl
     * @param string $url
     * @param mixed $data
     * @param bool $log_errors
     * @return string|bool
     */
    private static function processResponse($curl, $url, $data, $log_errors)
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
                    $response !== false ? $response : self::$last_response_code
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
                        'Payment error', 0, implode(', ', $errors),
                        $url, $data !== null ? json_encode($data) : null, $response
                    );
                }

                self::$last_errors = $errors;

                $response = json_encode(['errors' => self::$last_errors]);
            } elseif (self::$last_response_code >= 400) {
                $error_id = time();

                if ($log_errors) {
                    ErrorLogger::sendError(
                        "Payment error [$error_id]", self::$last_response_code,
                        'API error.', $url, $data !== null ? json_encode($data) : null, $response
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
     * @return string
     */
    private static function getUserAgentHeader()
    {
        return sprintf(
            'PS Comfino [%s], PS [%s], SF [%s], PHP [%s]',
            COMFINO_VERSION,
            _PS_VERSION_,
            COMFINO_PS_17 && class_exists('\Symfony\Component\HttpKernel\Kernel')
                ? \Symfony\Component\HttpKernel\Kernel::VERSION
                : 'n/a',
            PHP_VERSION
        );
    }
}
