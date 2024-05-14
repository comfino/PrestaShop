<?php

namespace Comfino;

use Comfino\Common\Shop\Cart;
use Comfino\Shop\Order\Cart\CartItem;
use Comfino\Shop\Order\Cart\CartItemInterface;
use Comfino\Shop\Order\Cart\Product;

class OrderManager
{
    public static function getShopCart(\Cart $cart): Cart
    {
        return new Cart(
            (int) ($cart->getOrderTotal(true) * 100),
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
