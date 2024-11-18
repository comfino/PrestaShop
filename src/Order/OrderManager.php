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

if (!defined('_PS_VERSION_')) {
    exit;
}

final class OrderManager
{
    public static function getShopCart(\Cart $cart, int $loanAmount, bool $loadProductCategories = false): Cart
    {
        $totalValue = (int) round(round($cart->getOrderTotal(), 2) * 100);

        if ($loanAmount > $totalValue) {
            // Loan amount with price modifier (e.g. custom commission).
            $totalValue = $loanAmount;
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
                        $loadProductCategories ? implode(',', self::getProductCategories($productEntity)) : null,
                        $product['ean13'],
                        self::getProductImageUrl($product),
                        \Product::getProductCategories($product['id_product']),
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
            $deliveryNetCost = (int) round(round($cart->getOrderTotal(false, \Cart::ONLY_SHIPPING), 2) * 100);
            $deliveryTaxValue = $deliveryCost - $deliveryNetCost;
            $deliveryTaxRate = (int) $carrier->getTaxesRate($cart->getAddressCollection()[$cart->id_address_delivery]);
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

    public static function getShopCartFromProduct(\Product $product, bool $loadProductCategories = false): Cart
    {
        $grossPrice = (int) round(round($product->getPrice(), 2) * 100);
        $netPrice = (int) round(round($product->getPrice(false), 2) * 100);

        return new Cart(
            $grossPrice,
            $netPrice,
            $grossPrice - $netPrice,
            0,
            null,
            null,
            null,
            [
                new CartItem(
                    new Product(
                        is_array($product->name) ? current($product->name) : $product->name,
                        (int) ($product->getPrice() * 100),
                        (string) $product->id,
                        $loadProductCategories ? implode(',', self::getProductCategories($product)) : null,
                        $product->ean13,
                        null,
                        array_map('intval', $product->getCategories()),
                        $product->getIdTaxRulesGroup() !== 0 ? (int) ($product->getPrice(false) * 100) : null,
                        $product->getIdTaxRulesGroup() !== 0 ? (int) $product->getTaxesRate() : null,
                        $product->getIdTaxRulesGroup() !== 0
                            ? (int) (($product->getPrice() - $product->getPrice(false)) * 100)
                            : null
                    ),
                    1
                ),
            ]
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
     * @return string[]
     */
    private static function getProductCategories(\Product $product): array
    {
        if (($categories = ConfigManager::getAllProductCategories()) === null) {
            return [];
        }

        return array_intersect_key($categories, array_flip($product->getCategories()));
    }
}
