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

require_once 'Order/Cart/CartItem.php';
require_once 'Order/Cart/Product.php';

use Comfino\Order\Cart;
use Comfino\Order\Cart\CartItem;
use Comfino\Order\Cart\Product;

final class OrderManager
{
    /**
     * @param \Cart $cart
     * @param int $priceModifier
     *
     * @return Cart
     *
     * @throws \Exception
     */
    public static function getShopCart(\Cart $cart, $priceModifier)
    {
        $totalValue = (int) round(round($cart->getOrderTotal(), 2) * 100);

        if ($priceModifier > 0) {
            // Add price modifier (e.g. custom commission).
            $totalValue += $priceModifier;
        }

        $cartItems = array_map(
            static function (array $product) {
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
                        null,
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
     *
     * @return Cart
     */
    public static function getShopCartFromOrder(\Order $order, int $priceModifier)
    {
        $totalValue = (int) round(round($order->getTotalProductsWithTaxes(), 2) * 100);
        $totalNetValue = (int) round(round($order->getTotalProductsWithoutTaxes(), 2) * 100);
        $totalTaxValue = $totalValue - $totalNetValue;

        if ($priceModifier > 0) {
            // Add price modifier (e.g. custom commission).
            $totalValue += $priceModifier;
        }

        $cartItems = array_map(
            static function (array $product) {
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
                        null,
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
     * @param \Product $product prestaShop product entity
     *
     * @return Cart comfino cart structure
     */
    public static function getShopCartFromProduct(\Product $product)
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
                        null,
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
     * @param array $product
     *
     * @return string
     */
    private static function getProductImageUrl(array $product)
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
    private static function getProductCategoryIds(\Product $product)
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
}
