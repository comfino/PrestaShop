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

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Extended\Api\Client;
use Comfino\Shop\Order\Cart;
use Comfino\Shop\Order\LoanParameters;
use Comfino\Shop\Order\Order;
use Psr\Http\Client\ClientExceptionInterface;
use Sunrise\Http\Factory\RequestFactory;
use Sunrise\Http\Factory\StreamFactory;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'ShopPluginErrorRequest.php';
require_once 'ErrorLogger.php';

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

    const INSTALLMENTS_ZERO_PERCENT = 'INSTALLMENTS_ZERO_PERCENT';
    const CONVENIENT_INSTALLMENTS = 'CONVENIENT_INSTALLMENTS';
    const PAY_LATER = 'PAY_LATER';

    /** @var Client */
    private static $api_client;

    /** @var \PaymentModule */
    private static $module;

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

    public static function init(\PaymentModule $module): void
    {
        self::$module = $module;

        $config_manager = ConfigManager::getInstance($module);

        self::$is_sandbox_mode = $config_manager->getConfigurationValue('COMFINO_IS_SANDBOX');
        self::$widget_key = $config_manager->getConfigurationValue('COMFINO_WIDGET_KEY');

        if (self::$is_sandbox_mode) {
            self::$api_host = self::COMFINO_SANDBOX_API_HOST;
            self::$api_key = $config_manager->getConfigurationValue('COMFINO_SANDBOX_API_KEY');
            self::$api_paywall_host = self::COMFINO_PAYWALL_SANDBOX_HOST;
            self::$paywall_frontend_script_url = self::COMFINO_PAYWALL_FRONTEND_JS_SANDBOX;
            self::$paywall_frontend_style_url = self::COMFINO_PAYWALL_FRONTEND_CSS_SANDBOX;
            self::$widget_script_url = self::COMFINO_WIDGET_JS_SANDBOX_HOST;

            $widget_dev_script_version = $config_manager->getConfigurationValue('COMFINO_WIDGET_DEV_SCRIPT_VERSION');

            if (empty($widget_dev_script_version)) {
                self::$widget_script_url .= '/comfino.min.js';
            } else {
                self::$widget_script_url .= ('/' . trim($widget_dev_script_version, '/'));
            }
        } else {
            self::$api_host = self::COMFINO_PRODUCTION_API_HOST;
            self::$api_key = $config_manager->getConfigurationValue('COMFINO_API_KEY');
            self::$api_paywall_host = self::COMFINO_PAYWALL_PRODUCTION_HOST;
            self::$paywall_frontend_script_url = self::COMFINO_PAYWALL_FRONTEND_JS_PRODUCTION;
            self::$paywall_frontend_style_url = self::COMFINO_PAYWALL_FRONTEND_CSS_PRODUCTION;
            self::$widget_script_url = self::COMFINO_WIDGET_JS_PRODUCTION_HOST;

            $widget_prod_script_version = $config_manager->getConfigurationValue('COMFINO_WIDGET_PROD_SCRIPT_VERSION');

            if (empty($widget_prod_script_version)) {
                self::$widget_script_url .= '/comfino.min.js';
            } else {
                self::$widget_script_url .= ('/' . trim($widget_prod_script_version, '/'));
            }
        }
    }

    public static function getInstance(\PaymentModule $module, ?bool $sandbox_mode = null): Client
    {
        self::$module = $module;

        if (self::$api_client === null) {
            $config_manager = ConfigManager::getInstance($module);

            if ($sandbox_mode === null) {
                $sandbox_mode = $config_manager->getConfigurationValue('COMFINO_IS_SANDBOX');
            }

            if ($sandbox_mode) {
                $api_key = $config_manager->getConfigurationValue('COMFINO_SANDBOX_API_KEY');
            } else {
                $api_key = $config_manager->getConfigurationValue('COMFINO_API_KEY');
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
                    COMFINO_VERSION,
                    _PS_VERSION_,
                    COMFINO_PS_17 && class_exists('\Symfony\Component\HttpKernel\Kernel')
                        ? \Symfony\Component\HttpKernel\Kernel::VERSION
                        : 'n/a',
                    PHP_VERSION,
                    \Tools::getShopDomain()
                ),
                $api_host,
                $module->context->language->iso_code
            );
        }

        return self::$api_client;
    }

    /**
     * @param \Cart $cart
     * @param string $order_id
     * @param string $return_url
     * @return array|bool
     */
    public static function createOrder($cart, $order_id, $return_url)
    {
        $context = \Context::getContext();

        $total = (int) ($cart->getOrderTotal(true) * 100);
        $delivery = (int) ($cart->getOrderTotal(true, \Cart::ONLY_SHIPPING) * 100);

        $loan_amount = (int) $context->cookie->loan_amount;

        if ($loan_amount > $total) {
            // Loan amount with price modifier (e.g. custom commission).
            $total = $loan_amount;
        }

        $config_manager = new \Comfino\ConfigManager(self::$module);
        $customer = new \Customer($cart->id_customer);
        $products = [];
        $allowed_product_types = null;
        $disabled_product_types = [];
        $available_product_types = array_map(
            static function (array $offer_type) { return $offer_type['key']; },
            $config_manager->getOfferTypes()
        );

        // Check product category filters.
        foreach ($available_product_types as $product_type) {
            if (!$config_manager->isFinancialProductAvailable($product_type, $cart->getProducts())) {
                $disabled_product_types[] = $product_type;
            }
        }

        if (count($disabled_product_types)) {
            $allowed_product_types = array_values(array_diff($available_product_types, $disabled_product_types));
        }

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

/*        if ($cart_total_with_delivery > $total) {
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
        }*/

        $address = $cart->getAddressCollection();
        $address_explode = explode(' ', $address[$cart->id_address_delivery]->address1);
        $building_number = '';

        if (count($address_explode) === 2) {
            $building_number = $address_explode[1];
        }

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

        if ($allowed_product_types !== null) {
            $data['loanParameters']['allowedProductTypes'] = $allowed_product_types;
        }

        if (preg_match('/^[A-Z]{0,3}\d{7,}$/', $customer_tax_id)) {
            $data['customer']['taxId'] = $customer_tax_id;
        }

        //-----------------------------
        $client = new Client(
            new RequestFactory(),
            new StreamFactory(),
            new \Sunrise\Http\Client\Curl\Client(new \Sunrise\Http\Factory\ResponseFactory()),
            self::getApiKey()
        );

        $client->setCustomApiHost(self::getApiHost());
        $client->setCustomUserAgent(self::getUserAgentHeader());

        if ($allowed_product_types !== null) {
            $allowed_product_types = array_map(
                static function (string $product_type): LoanTypeEnum { return new LoanTypeEnum($product_type); },
                $allowed_product_types
            );
        }

        $cart_items = [];

        foreach ($products as $product) {
            $cart_items[] = new Cart\CartItem(
                new Cart\Product(
                    $product['name'],
                    $product['price'],
                    $product['externalId'],
                    $product['category'],
                    $product['ean'],
                    $product['photoUrl']
                    //self::getProductsImageUrl($product)
                ),
                $product['quantity']
            );
        }

        $order = new Order(
            (string) $order_id,
            self::getReturnUrl($return_url),
            new LoanParameters(
                $total,
                (int) $context->cookie->loan_term,
                new LoanTypeEnum($context->cookie->loan_type),
                $allowed_product_types
            ),
            new Cart($cart_items, $total, $delivery, 'Kategoria'),
            new Shop\Order\Customer(
                $address[$cart->id_address_delivery]->firstname,
                $address[$cart->id_address_delivery]->lastname,
                $customer->email,
                $phone_number,
                \Tools::getRemoteAddr(),
                preg_match('/^[A-Z]{0,3}\d{7,}$/', $customer_tax_id) ? $customer_tax_id : null,
                !$customer->is_guest,
                $customer->isLogged(),
                new \Comfino\Shop\Order\Customer\Address(
                    $address_explode[0],
                    $building_number,
                    null,
                    $address[$cart->id_address_delivery]->postcode,
                    $address[$cart->id_address_delivery]->city,
                    'PL'
                )
            ),
            self::getNotifyUrl($context)
        );

        try {
            $response = $client->createOrder($order);

            return [
                'externalId' => $response->externalId,
                'applicationUrl' => $response->applicationUrl
            ];
        } catch (RequestValidationError $e) {
            return ['errors' => [$e->getMessage()]];
        } catch (AuthorizationError $e) {
            return ['errors' => [$e->getMessage()]];
        } catch (AccessDenied $e) {
            return ['errors' => [$e->getMessage()]];
        } catch (ServiceUnavailable $e) {
            return ['errors' => [$e->getMessage()]];
        } catch (ClientExceptionInterface $e) {
            return ['errors' => [$e->getMessage()]];
        }

        //-----------------------------

        /*$response = self::sendRequest(
            self::getApiHost() . '/v1/orders',
            'POST',
            [CURLOPT_FOLLOWLOCATION => true],
            $data
        );*/

        //return $response !== false ? json_decode($response, true) : false;
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

    /**
     * @return string[]|bool
     */
    public static function getWidgetTypes()
    {
        static $product_types = null;

        if ($product_types === null) {
            $product_types = self::sendRequest(self::getApiHost() . '/v1/widget-types', 'GET');

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
     * @param string $name
     * @param string $url
     * @param string $contact_name
     * @param string $email
     * @param string $phone
     * @param array $agreements
     * @return array|bool
     */
    public static function registerShopAccount($name, $url, $contact_name, $email, $phone, $agreements)
    {
        $data = [
            'name' => $name,
            'webSiteUrl' => $url,
            'contactName' => $contact_name,
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
        return self::getApiHost(true) . '/v1/get-logo-url?auth=' . self::getLogoAuthHash();
    }

    /**
     * @return string
     */
    public static function getPaywallLogoUrl()
    {
        return self::getApiHost(true) . '/v1/get-paywall-logo?auth=' . self::getLogoAuthHash(true);
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

        return $api_host !== null ? $api_host : self::$api_host;
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
