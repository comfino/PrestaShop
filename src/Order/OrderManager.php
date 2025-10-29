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

namespace Comfino\Order;

use Comfino\Common\Shop\Cart;
use Comfino\Configuration\ConfigManager;
use Comfino\Shop\Order\Cart\CartItem;
use Comfino\Shop\Order\Cart\CartItemInterface;
use Comfino\Shop\Order\Cart\Product;
use Comfino\Shop\Order\Customer;
use Comfino\Shop\Order\Customer\Address;
use Comfino\Tools;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class OrderManager
{
    public static function getShopCart(\Cart $cart, int $priceModifier, bool $loadProductCategories = false): Cart
    {
        $totalValue = (int) round(round($cart->getOrderTotal(), 2) * 100);

        if ($priceModifier > 0) {
            // Add price modifier (e.g. custom commission).
            $totalValue += $priceModifier;
        }

        $cartItems = array_map(
            static function (array $product) use ($loadProductCategories): CartItemInterface {
                $productEntity = new \Product($product['id_product']);

                $quantity = (int) $product['cart_quantity'];
                $taxRulesGroupId = \Product::getIdTaxRulesGroupByIdProduct($product['id_product']);
                $grossPrice = (int) round(round(\Product::getPriceStatic($product['id_product']), 2) * 100);
                $netPrice = (int) round(round(\Product::getPriceStatic($product['id_product'], false), 2) * 100);

                return new CartItem(
                    new Product(
                        $product['name'],
                        $grossPrice,
                        (string) $product['id_product'],
                        $loadProductCategories ? implode('→', self::getProductCategoryNames($productEntity)) : null,
                        $product['ean13'],
                        self::getProductImageUrl($product),
                        self::getProductCategoryIds($productEntity),
                        $taxRulesGroupId !== 0 ? $netPrice : null,
                        $taxRulesGroupId !== 0 ? (int) $productEntity->getTaxesRate() : null,
                        $taxRulesGroupId !== 0 ? $grossPrice - $netPrice : null
                    ),
                    $quantity
                );
            },
            $cart->getProducts()
        );

        $totalNetValue = 0;
        $totalTaxValue = 0;

        foreach ($cartItems as $cartItem) {
            if ($cartItem->getProduct()->getNetPrice() !== null) {
                $totalNetValue += ($cartItem->getProduct()->getNetPrice() * $cartItem->getQuantity());
            }

            if ($cartItem->getProduct()->getTaxValue() !== null) {
                $totalTaxValue += ($cartItem->getProduct()->getTaxValue() * $cartItem->getQuantity());
            }
        }

        if ($totalNetValue === 0) {
            $totalNetValue = null;
        }

        if ($totalTaxValue === 0) {
            $totalTaxValue = null;
        }

        $deliveryCost = (int) round(round($cart->getOrderTotal(true, \Cart::ONLY_SHIPPING), 2) * 100);
        $deliveryNetCost = null;
        $deliveryTaxValue = null;
        $deliveryTaxRate = null;

        if (\Validate::isLoadedObject($carrier = new \Carrier($cart->id_carrier))
            && \Carrier::getIdTaxRulesGroupByIdCarrier($cart->id_carrier) !== 0
        ) {
            $deliveryAddress = $cart->getAddressCollection()[$cart->id_address_delivery];
            $billingAddress = $cart->getAddressCollection()[$cart->id_address_invoice];

            $deliveryNetCost = (int) round(round($cart->getOrderTotal(false, \Cart::ONLY_SHIPPING), 2) * 100);
            $deliveryTaxValue = $deliveryCost - $deliveryNetCost;

            if ($deliveryAddress !== null) {
                $deliveryTaxRate = (int) $carrier->getTaxesRate($deliveryAddress);
            } elseif ($billingAddress !== null) {
                $deliveryTaxRate = (int) $carrier->getTaxesRate($billingAddress);
            } elseif ($deliveryCost !== 0) {
                $deliveryTaxRate = (int) (round($deliveryTaxValue / $deliveryCost, 2) * 100);
            }
        }

        return new Cart(
            $totalValue,
            $totalNetValue,
            $totalTaxValue,
            $deliveryCost,
            $deliveryNetCost,
            $deliveryTaxRate,
            $deliveryTaxValue,
            $cartItems
        );
    }

    /**
     * @param \Order $order
     * @param int $priceModifier
     * @param bool $loadProductCategories Whether to load product category names into cart items.
     *
     * @return Cart Comfino cart structure.
     */
    public static function getShopCartFromOrder(\Order $order, int $priceModifier, bool $loadProductCategories = false): Cart
    {
        $totalValue = (int) round(round($order->getTotalProductsWithTaxes(), 2) * 100);
        $totalNetValue = (int) round(round($order->getTotalProductsWithoutTaxes(), 2) * 100);
        $totalTaxValue = $totalValue - $totalNetValue;

        if ($priceModifier > 0) {
            // Add price modifier (e.g. custom commission).
            $totalValue += $priceModifier;
        }

        $cartItems = array_map(
            static function (array $product) use ($loadProductCategories): CartItemInterface {
                $productEntity = new \Product($product['id_product']);

                $quantity = (int) $product['cart_quantity'];
                $taxRulesGroupId = \Product::getIdTaxRulesGroupByIdProduct($product['id_product']);
                $grossPrice = (int) round(round(\Product::getPriceStatic($product['id_product']), 2) * 100);
                $netPrice = (int) round(round(\Product::getPriceStatic($product['id_product'], false), 2) * 100);

                return new CartItem(
                    new Product(
                        $product['name'],
                        $grossPrice,
                        (string) $product['id_product'],
                        $loadProductCategories ? implode('→', self::getProductCategoryNames($productEntity)) : null,
                        $product['ean13'],
                        self::getProductImageUrl($product),
                        self::getProductCategoryIds($productEntity),
                        $taxRulesGroupId !== 0 ? $netPrice : null,
                        $taxRulesGroupId !== 0 ? (int) $productEntity->getTaxesRate() : null,
                        $taxRulesGroupId !== 0 ? $grossPrice - $netPrice : null
                    ),
                    $quantity
                );
            },
            $order->getCartProducts()
        );

        if ($totalNetValue === 0) {
            $totalNetValue = null;
        }

        if ($totalTaxValue === 0) {
            $totalTaxValue = null;
        }

        $deliveryCost = (int) round(round($order->total_shipping, 2) * 100);
        $deliveryNetCost = (int) round(round($order->total_shipping_tax_excl, 2) * 100);
        $deliveryTaxValue = $deliveryCost - $deliveryNetCost;
        $deliveryTaxRate = (int) round(round($order->carrier_tax_rate, 2) * 100);

        return new Cart(
            $totalValue,
            $totalNetValue,
            $totalTaxValue,
            $deliveryCost,
            $deliveryNetCost,
            $deliveryTaxRate,
            $deliveryTaxValue,
            $cartItems
        );
    }

    /**
     * @param \Product $product PrestaShop product entity.
     * @param bool $loadProductCategories Whether to load product category names into cart items.
     *
     * @return Cart Comfino cart structure.
     */
    public static function getShopCartFromProduct(\Product $product, bool $loadProductCategories = false): Cart
    {
        $taxRate = ($product->getIdTaxRulesGroup() !== 0 ? (int) $product->getTaxesRate() : null);
        $grossPrice = (int) round(round($product->getPrice(), 2) * 100);
        $netPrice = (int) round(round($product->getPrice(false), 2) * 100);
        $taxValue = ($taxRate !== null ? $grossPrice - $netPrice : null);

        return new Cart(
            $grossPrice,
            $netPrice,
            $taxValue,
            0,
            null,
            null,
            null,
            [
                new CartItem(
                    new Product(
                        is_array($product->name) ? current($product->name) : $product->name,
                        $grossPrice,
                        (string) $product->id,
                        $loadProductCategories ? implode('→', self::getProductCategoryNames($product)) : null,
                        $product->ean13,
                        null,
                        self::getProductCategoryIds($product),
                        $taxRate !== null ? $netPrice : null,
                        $taxRate,
                        $taxValue
                    ),
                    1
                ),
            ]
        );
    }

    /**
     * @param \Cart $cart PrestaShop cart entity.
     * @param \Customer $customer PrestaShop customer entity.
     * @param \Context $context PrestaShop context.
     *
     * @return Customer Comfino customer structure.
     */
    public static function getShopCustomerFromCart(\Cart $cart, \Customer $customer, \Context $context): Customer
    {
        $billingAddress = $cart->getAddressCollection()[$cart->id_address_invoice];
        $deliveryAddress = $cart->getAddressCollection()[$cart->id_address_delivery];

        if ($billingAddress === null) {
            $billingAddress = $deliveryAddress;
        }

        $phoneNumber = trim($billingAddress->phone ?? '');

        if (empty($phoneNumber)) {
            $phoneNumber = trim($billingAddress->phone_mobile ?? '');
        }

        if (!empty(trim($deliveryAddress->phone))) {
            $phoneNumber = trim($deliveryAddress->phone);
        }

        if (!empty(trim($deliveryAddress->phone_mobile))) {
            $phoneNumber = trim($deliveryAddress->phone_mobile);
        }

        if (!empty(trim($billingAddress->firstname ?? ''))) {
            // Use billing address to get customer names.
            [$firstName, $lastName] = self::prepareCustomerNames($billingAddress);
        } else {
            // Use delivery address to get customer names.
            [$firstName, $lastName] = self::prepareCustomerNames($deliveryAddress);
        }

        $billingAddressLines = $billingAddress->address1;

        if (!empty($billingAddress->address2)) {
            $billingAddressLines .= " $billingAddress->address2";
        }

        if (empty($billingAddressLines)) {
            $deliveryAddressLines = $deliveryAddress->address1;

            if (!empty($deliveryAddress->address2)) {
                $deliveryAddressLines .= " {$deliveryAddress->address2}";
            }

            $street = trim($deliveryAddressLines);
        } else {
            $street = trim($billingAddressLines);
        }

        $addressParts = explode(' ', $street);
        $buildingNumber = '';

        if (count($addressParts) > 1) {
            foreach ($addressParts as $idx => $addressPart) {
                if (preg_match('/^\d+[a-zA-Z]?$/', trim($addressPart))) {
                    $street = implode(' ', array_slice($addressParts, 0, $idx));
                    $buildingNumber = trim($addressPart);
                }
            }
        }

        $customerTaxId = trim(str_replace('-', '', $billingAddress->vat_number ?? ''));

        return new Customer(
            $firstName,
            $lastName,
            $customer->email,
            $phoneNumber,
            \Tools::getRemoteAddr(),
            preg_match('/^[A-Z]{0,3}\d{7,}$/', $customerTaxId) ? $customerTaxId : null,
            !$customer->is_guest,
            $customer->isLogged(),
            new Address(
                $street,
                $buildingNumber,
                null,
                !empty($deliveryAddress->postcode),
                $deliveryAddress->city,
                (new Tools($context))->getCountryIsoCode($deliveryAddress->id_country)
            )
        );
    }

    public static function checkCartCurrency(\PaymentModule $module, \Cart $cart): bool
    {
        $currencyOrder = new \Currency($cart->id_currency);
        $currenciesModule = $module->getCurrency($cart->id_currency);

        if (is_array($currenciesModule)) {
            foreach ($currenciesModule as $currencyModule) {
                if ((int) $currencyOrder->id === (int) $currencyModule['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function getProductImageUrl(array $product): string
    {
        $linkRewrite = is_array($product['link_rewrite']) ? end($product['link_rewrite']) : $product['link_rewrite'];

        if ($linkRewrite === false) {
            return '';
        }

        $image = \Image::getCover($product['id_product']);

        if (!is_array($image) && !isset($image['id_image'])) {
            return '';
        }

        $imageUrl = (new \Link())->getImageLink($linkRewrite, $image['id_image']);

        return strpos($imageUrl, 'http') === false ? "https://$imageUrl" : $imageUrl;
    }

    /**
     * @return int[]
     */
    private static function getProductCategoryIds(\Product $product): array
    {
        $categoryIds = [];

        foreach ($product->getCategories() as $categoryId) {
            $category = new \Category($categoryId);

            if (\Validate::isLoadedObject($category) && $category->active) {
                $categoryIds[] = (int) $categoryId;
            }
        }

        return $categoryIds;
    }

    /**
     * @return string[]
     */
    private static function getProductCategoryNames(\Product $product): array
    {
        if (($categories = ConfigManager::getAllProductCategories()) === null) {
            return [];
        }

        return array_intersect_key($categories, array_flip(self::getProductCategoryIds($product)));
    }

    private static function prepareCustomerNames(\Address $address): array
    {
        $firstName = trim($address->firstname ?? '');
        $lastName = trim($address->lastname ?? '');

        if (empty($lastName)) {
            $nameParts = explode(' ', $firstName);

            if (count($nameParts) > 1) {
                [$firstName, $lastName] = $nameParts;
            }
        }

        return [$firstName, $lastName];
    }
}
