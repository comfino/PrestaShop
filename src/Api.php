<?php
/**
 * 2007-2022 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'ShopPluginErrorRequest.php';
require_once 'ErrorLogger.php';

class ComfinoApi
{
    const COMFINO_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    const COMFINO_SANDBOX_HOST = 'https://api-ecommerce.ecraty.pl';

    const INSTALLMENTS_ZERO_PERCENT = 'INSTALLMENTS_ZERO_PERCENT';
    const CONVENIENT_INSTALLMENTS = 'CONVENIENT_INSTALLMENTS';
    const PAY_LATER = 'PAY_LATER';

    private static $api_host;
    private static $api_key;
    private static $last_request_body;

    /**
     * @param Cart $cart
     * @param string $order_id
     * @param string $return_url
     *
     * @return array|bool
     */
    public static function createOrder($cart, $order_id, $return_url)
    {
        $total = (int) floor(((float) $cart->getOrderTotal(true)) * 100);
        $delivery = (int) floor(((float) $cart->getOrderTotal(true, Cart::ONLY_SHIPPING)) * 100);

        $customer = new Customer($cart->id_customer);
        $products = [];

        foreach ($cart->getProducts() as $product) {
            $products[] = [
                'name' => $product['name'],
                'quantity' => (int) $product['cart_quantity'],
                'price' => (int) ($product['total_wt'] / $product['cart_quantity'] * 100),
                'photoUrl' => self::getProductsImageUrl($product),
                'ean' => $product['ean13'],
                'externalId' => (string) $product['id_product'],
                'category' => $product['category'],
            ];
        }

        $address = $cart->getAddressCollection();
        $address_explode = explode(' ', $address[$cart->id_address_delivery]->address1);
        $building_number = '';

        if (count($address_explode) === 2) {
            $building_number = $address_explode[1];
        }

        $context = Context::getContext();
        $customer_tax_id = trim(str_replace('-', '', $address[$cart->id_address_delivery]->vat_number));

        $data = [
            'notifyUrl' => $context->link->getModuleLink($context->controller->module->name, 'notify', [], true),
            'returnUrl' => Tools::getHttpHost(true) . __PS_BASE_URI__ . $return_url,
            'orderId' => (string) $order_id,
            'draft' => false,
            'loanParameters' => [
                'amount' => $total,
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
                'phoneNumber' => !empty($address[$cart->id_address_delivery]->phone)
                    ? $address[$cart->id_address_delivery]->phone
                    : $address[$cart->id_address_delivery]->phone_mobile,
                'ip' => Tools::getRemoteAddr(),
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

        $response = self::sendRequest(self::getApiHost() . '/v1/orders', 'POST', [CURLOPT_FOLLOWLOCATION => true], $data);

        return $response !== false ? json_decode($response, true) : false;
    }

    /**
     * @param $loan_amount
     *
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
     *
     * @return array|bool
     */
    public static function getOrder($self_link)
    {
        $response = self::sendRequest(str_replace('https', 'http', $self_link), 'GET');

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
     * @return string|bool
     */
    public static function getWidgetKey()
    {
        $widgetKey = '';

        if (!empty(self::getApiKey())) {
            $widgetKey = self::sendRequest(self::getApiHost() . '/v1/widget-key', 'GET');

            if (!is_array($widgetKey)) {
                $widgetKey = json_decode($widgetKey, true);
            }
        }

        return $widgetKey;
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

        return strpos($response, '"errors":') === false;
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
        return self::getApiHost() . '/v1/get-logo-url';
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
     * @return string|null
     */
    public static function getLastRequestBody()
    {
        return self::$last_request_body;
    }

    /**
     * @return string
     */
    private static function getApiHost()
    {
        return empty(self::$api_host)
            ? Configuration::get('COMFINO_IS_SANDBOX') ? self::COMFINO_SANDBOX_HOST : self::COMFINO_PRODUCTION_HOST
            : self::$api_host;
    }

    /**
     * @return string
     */
    private static function getApiKey()
    {
        return empty(self::$api_key)
            ? Configuration::get('COMFINO_IS_SANDBOX')
                ? Configuration::get('COMFINO_SANDBOX_API_KEY')
                : Configuration::get('COMFINO_API_KEY')
            : self::$api_key;
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

        $image = Image::getCover($product['id_product']);

        if (!is_array($image) && !isset($image['id_image'])) {
            return '';
        }

        $imageUrl = (new Link())->getImageLink($link_rewrite, $image['id_image']);

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

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => Tools::strtoupper($request_type),
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

        return $response;
    }

    /**
     * @param resource $curl
     * @param string $url
     * @param mixed $data
     * @param bool $log_errors
     *
     * @return string|bool
     */
    private static function processResponse($curl, $url, $data, $log_errors)
    {
        $response = curl_exec($curl);

        if ($response === false || (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE) >= 400) {
            $error_id = time();

            if ($log_errors) {
                ErrorLogger::sendError(
                    "Communication error [$error_id]", curl_errno($curl), curl_error($curl),
                    $url, $data !== null ? json_encode($data) : null, $response
                );
            }

            $response = json_encode([
                'errors' => ["Communication error: $error_id. Please contact with support and note this error id."],
            ]);
        } else {
            $decoded = json_decode($response, true);

            if ($decoded !== false && isset($decoded['errors'])) {
                if ($log_errors) {
                    ErrorLogger::sendError(
                        'Payment error', 0, implode(', ', $decoded['errors']),
                        $url, $data !== null ? json_encode($data) : null, $response
                    );
                }

                $response = json_encode(['errors' => array_values($decoded['errors'])]);
            } elseif (curl_getinfo($curl, CURLINFO_RESPONSE_CODE) >= 400) {
                $error_id = time();

                if ($log_errors) {
                    ErrorLogger::sendError(
                        "Payment error [$error_id]", curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
                        'API error.', $url, $data !== null ? json_encode($data) : null, $response
                    );
                }

                $response = json_encode([
                    'errors' => ["Payment error: $error_id. Please contact with support and note this error id."],
                ]);
            }
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
