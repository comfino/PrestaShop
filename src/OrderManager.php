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

use Comfino\Common\Shop\Cart;
use Comfino\Shop\Order\Cart\CartItem;
use Comfino\Shop\Order\Cart\CartItemInterface;
use Comfino\Shop\Order\Cart\Product;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class OrderManager
{
    public static function getShopCart(\Cart $cart, int $loan_amount): Cart
    {
        $total = (int) ($cart->getOrderTotal(true) * 100);

        if ($loan_amount > $total) {
            // Loan amount with price modifier (e.g. custom commission).
            $total = $loan_amount;
        }

        return new Cart(
            $total,
            (int) ($cart->getOrderTotal(true, \Cart::ONLY_SHIPPING) * 100),
            array_map(static function (array $product): CartItemInterface {
                $quantity = (int) $product['cart_quantity'];

                return new CartItem(
                    new Product(
                        $product['name'],
                        (int) ($product['total_wt'] / $quantity * 100),
                        (string) $product['id_product'],
                        $product['category'],
                        $product['ean13'],
                        self::getProductImageUrl($product),
                        \Product::getProductCategories($product['id_product'])
                    ),
                    $quantity
                );
            }, $cart->getProducts())
        );
    }

    public static function getShopCartFromProduct(\Product $product): Cart
    {
        return new Cart(
            (int) ($product->getPrice() * 100),
            0,
            [
                new CartItem(
                    new Product(
                        is_array($product->name) ? current($product->name) : $product->name,
                        (int) ($product->getPrice() * 100),
                        (string) $product->id,
                        null,
                        null,
                        null,
                        array_map('intval', $product->getCategories())
                    ),
                    1
                ),
            ]
        );
    }

    public static function checkCartCurrency(\PaymentModule $module, \Cart $cart): bool
    {
        $currency_order = new \Currency($cart->id_currency);
        $currencies_module = $module->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ((int) $currency_order->id === (int) $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function validateCustomerData(\PaymentModule $module, array $params): string
    {
        $vat_number = $params['form']->getField('vat_number');
        $tools = new Tools(\Context::getContext());

        if (!empty($vat_number->getValue()) && !$tools->isValidTaxId($vat_number->getValue())) {
            $vat_number->addError($module->l('Invalid VAT number.'));

            return '0';
        }

        return '1';
    }

    private static function getProductImageUrl(array $product): string
    {
        $link_rewrite = is_array($product['link_rewrite']) ? end($product['link_rewrite']) : $product['link_rewrite'];

        if ($link_rewrite === false) {
            return '';
        }

        $image = \Image::getCover($product['id_product']);

        if (!is_array($image) && !isset($image['id_image'])) {
            return '';
        }

        $image_url = (new \Link())->getImageLink($link_rewrite, $image['id_image']);

        return strpos($image_url, 'http') === false ? "https://$image_url" : $image_url;
    }
}
