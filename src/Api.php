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

    private static $api_host;
    private static $api_key;
    private static $last_request_body;

    /**
     * @param Cart $cart_data
     * @param string $order_id
     * @param string $return_url
     *
     * @return bool|string
     */
    public static function createOrder($cart_data, $order_id, $return_url)
    {
        $total = (int) floor(((float) $cart_data->getOrderTotal(true)) * 100);
        $delivery = (int) floor(((float) $cart_data->getOrderTotal(true, Cart::ONLY_SHIPPING)) * 100);

        $customer = new Customer($cart_data->id_customer);
        $products = [];

        foreach ($cart_data->getProducts() as $product) {
            $products[] = [
                'name' => $product['name'],
                'quantity' => (int) $product['cart_quantity'],
                'price' => (int) ($product['total_wt'] / $product['cart_quantity'] * 100),
                'photoUrl' => self::getProductsImageUrl($product),
                'ean' => $product['ean13'],
                'externalId' => (string) $product['id_product'],
                'category' => $product['category']
            ];
        }

        $address = $cart_data->getAddressCollection();
        $address_explode = explode(' ', $address[$cart_data->id_address_delivery]->address1);
        $building_number = '';

        if (count($address_explode) === 2) {
            $building_number = $address_explode[1];
        }

        $context = Context::getContext();
        $customerTaxId = trim(str_replace('-', '', $address[$cart_data->id_address_delivery]->vat_number));

        $data = [
            'notifyUrl' => $context->link->getModuleLink($context->controller->module->name, 'notify', [], true),
            'returnUrl' => Tools::getHttpHost(true).__PS_BASE_URI__.$return_url,
            'orderId' => (string) $order_id,
            'draft' => false,
            'loanParameters' => [
                'amount' => $total,
                'term' => (int) $context->cookie->loan_term,
                'type' => $context->cookie->loan_type
            ],
            'cart' => [
                'category' => 'Kategoria',
                'totalAmount' => $total,
                'deliveryCost' => $delivery,
                'products' => $products
            ],
            'customer' => [
                'firstName' => $address[$cart_data->id_address_delivery]->firstname,
                'lastName' => $address[$cart_data->id_address_delivery]->lastname,
                'email' => $customer->email,
                'phoneNumber' => !empty($address[$cart_data->id_address_delivery]->phone)
                    ? $address[$cart_data->id_address_delivery]->phone
                    : $address[$cart_data->id_address_delivery]->phone_mobile,
                'ip' => Tools::getRemoteAddr(),
                'regular' => !$customer->is_guest,
                'logged' => $customer->isLogged(),
                'address' => [
                    'street' => $address_explode[0],
                    'buildingNumber' => $building_number,
                    'apartmentNumber' => '',
                    'postalCode' => $address[$cart_data->id_address_delivery]->postcode,
                    'city' => $address[$cart_data->id_address_delivery]->city,
                    'countryCode' => 'PL'
                ]
            ],
            'seller' => [
                'taxId' => Configuration::get("COMFINO_TAX_ID")
            ]
        ];

        if (preg_match('/^[A-Z]{0,3}\d{7,}$/', $customerTaxId)) {
            $data['customer']['taxId'] = $customerTaxId;
        }

        return json_decode(
            self::sendRequest(self::getApiHost().'/v1/orders', 'POST', [CURLOPT_FOLLOWLOCATION => true], $data),
            true
        );
    }

    /**
     * @param $loanAmount
     *
     * @return bool|string
     */
    public static function getOffer($loanAmount)
    {
        $loanAmount = (float) $loanAmount;

        return self::sendRequest(self::getApiHost()."/v1/financial-products?loanAmount=$loanAmount", 'GET');
    }

    /**
     * @param $self_link
     *
     * @return bool|string
     */
    public static function getOrder($self_link)
    {
        return self::sendRequest(str_replace('https', 'http', $self_link), 'GET');
    }

    /**
     * @param string $order_id
     */
    public static function cancelOrder($order_id)
    {
        self::sendRequest(self::getApiHost()."/v1/orders/$order_id/cancel", 'PUT');
    }

    /**
     * @return string|bool
     */
    public static function getWidgetKey()
    {
        $widgetKey = '';

        if (!empty(self::getApiKey())) {
            $widgetKey = self::sendRequest(self::getApiHost().'/v1/widget-key', 'GET');

            if (!is_array($widgetKey)) {
                $widgetKey = json_decode($widgetKey, true);
            }
        }

        return $widgetKey;
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
        $response = self::sendRequest(self::getApiHost().'/v1/log-plugin-error', 'POST', [], $data, false);

        return strpos($response, '"errors":') === false;
    }

    public static function getLogoUrl()
    {
        return self::getApiHost().'/v1/get-logo-url';
    }

    public static function setApiHost($api_host)
    {
        self::$api_host = $api_host;
    }

    public static function setApiKey($api_key)
    {
        self::$api_key = $api_key;
    }

    public static function getLastRequestBody()
    {
        return self::$last_request_body;
    }

    private static function getApiHost()
    {
        return empty(self::$api_host)
            ? Configuration::get('COMFINO_IS_SANDBOX') ? self::COMFINO_SANDBOX_HOST : self::COMFINO_PRODUCTION_HOST
            : self::$api_host;
    }

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
            $imageUrl = 'https://'.$imageUrl;
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
     * @return bool|string
     */
    private static function sendRequest($url, $request_type, $extra_options = [], $data = null, $log_errors = true)
    {
        self::$last_request_body = null;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => Tools::strtoupper($request_type),
            CURLOPT_HTTPHEADER => [
                'API-KEY: '.self::getApiKey(),
                'User-Agent: '.self::getUserAgentHeader(),
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
                'errors' => ["Communication error: $error_id. Please contact with support and note this error id."]
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
                    'errors' => ["Payment error: $error_id. Please contact with support and note this error id."]
                ]);
            }
        }

        return $response;
    }

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
