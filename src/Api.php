<?php
/**
 * 2007-2021 PrestaShop
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
 * @author PrestaShop SA <contact@prestashop.com>
 * @copyright  2007-2021 PrestaShop SA
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version  Release: $Revision$
 *  International Registered Trademark & Property of PrestaShop SA
 */

class ComfinoApi
{
    /**
     * @param Cart $cart_data
     * @param $order_id
     *
     * @return bool|string
     */
    public static function createOrder($cart_data, $order_id, $return_url)
    {
        $total = ((float)$cart_data->getOrderTotal(true)) * 100;
        $delivery = ((float)$cart_data->getCarrierCost($cart_data->id_carrier)) * 100;

        $customer = new Customer($cart_data->id_customer);
        $products = [];
        foreach ($cart_data->getProducts() as $product) {
            $product_object = new Product($product['id_product']);

            $products[] = [
                'name' => $product['name'],
                'quantity' => (int)$product['cart_quantity'],
                'price' => (int) round($product_object->getPrice(), 2) * 100,
                'photoUrl' => self::getProductsImageUrl($product_object),
                'ean' => $product_object->ean13,
                'externalId' => (string)$product_object->id,
                'category' => $product['category']
            ];
        }

        $address = $cart_data->getAddressCollection();
        $address_explode = explode(' ', $address[$cart_data->id_address_delivery]->address1);
        $buildingNumber = '';
        if (count($address_explode) == 2) {
            $buildingNumber = $address_explode[1];
        }

        $street = $address_explode[0];

        $cookie = Context::getContext()->cookie;

        $data = [
            'notifyUrl' => Tools::getHttpHost(true) . __PS_BASE_URI__ . 'module/comfino/notify',
            'returnUrl' => Tools::getHttpHost(true) . __PS_BASE_URI__ . $return_url,
            'orderId' => (string)$order_id,
            'draft' => false,
            'loanParameters' => [
                'term' => (int) $cookie->loan_term,
                'type' => $cookie->loan_type
            ],
            'cart' => [
                'category' => 'Kategoria',
                'totalAmount' => (int) $total,
                'deliveryCost' => (int) $delivery,
                'products' => $products
            ],
            'customer' => [
                'firstName' => $address[$cart_data->id_address_delivery]->firstname,
                'lastName' => $address[$cart_data->id_address_delivery]->lastname,
                'taxId' => $address[$cart_data->id_address_delivery]->vat_number,
                'email' => $customer->email,
                'phoneNumber' => $address[$cart_data->id_address_delivery]->phone,
                'ip' => Tools::getRemoteAddr(),
                'regular' => !$customer->is_guest,
                'logged' => $customer->isLogged(),
                'address' => [
                    'street' => $street,
                    'buildingNumber' => $buildingNumber,
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


        $host = Configuration::get('COMFINO_PRODUCTION_HOST');
        if ((bool)Configuration::get('COMFINO_IS_SANDBOX')) {
            $host = Configuration::get('COMFINO_SANDBOX_HOST');
        }

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL => $host . '/v1/orders',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Api-Key: ' . Configuration::get('COMFINO_API_KEY'),
                    'Content-Type: application/json',
                    'User-Agent: PrestaShop'
                ]
            ]
        );

        $response = curl_exec($curl);

        $decoded = json_decode($response, true);
        if (isset($decoded['errors'])) {
            $errors = [];
            foreach ($decoded['errors'] as $value) {
                $errors[] = $value;
            }
            file_put_contents(
                "." . _MODULE_DIR_ . "/comfino/payment_log.log",
                "[" . date('Y-m-d H:i:s') . "] Payment data: " . print_r($data, true),
                FILE_APPEND
            );

            file_put_contents(
                "." . _MODULE_DIR_ . "/comfino/payment_log.log",
                "[" . date('Y-m-d H:i:s') . "] Response data: " . print_r($response, true),
                FILE_APPEND
            );

            return json_encode(['errors' => $errors]);
        }
        curl_close($curl);
        return $response;
    }

    /**
     * @param $product
     *
     * @return string
     */
    private static function getProductsImageUrl($product)
    {
        $link_rewrite = "";
        if (is_array($product->link_rewrite)) {
            foreach ($product->link_rewrite as $link) {
                $link_rewrite = $link;
            }
        } else {
            $link_rewrite = $product->link_rewrite;
        }

        $image = Image::getCover($product->id);
        if (!is_array($image) && !isset($image['id_image'])) {
            return "";
        }

        $link = new Link();
        return $link->getImageLink($link_rewrite, $image['id_image']);
    }

    /**
     * @param $loanTerm
     * @param $loanAmount
     *
     * @return bool|string
     */
    public static function getOffer($loanTerm, $loanAmount)
    {
        $curl = curl_init();
        $loanAmount = (float)$loanAmount;

        $url = Configuration::get('COMFINO_PRODUCTION_HOST');

        if ((bool)Configuration::get('COMFINO_IS_SANDBOX')) {
            $url = Configuration::get('COMFINO_SANDBOX_HOST');
        }

        $url .= "/v1/financial-products?loanAmount={$loanAmount}&loanTerm={$loanTerm}";

        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'API-KEY: ' . Configuration::get('COMFINO_API_KEY'),
                ]
            ]
        );
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    /**
     * @param $self_link
     *
     * @return bool|string
     */
    public static function getOrder($self_link)
    {
        $curl = curl_init();

        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL => $self_link,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'API-KEY: ' . Configuration::get('COMFINO_API_KEY')
                ]
            ]
        );
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }
}
