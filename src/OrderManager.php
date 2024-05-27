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

final class OrderManager
{
    const CREATED = 'CREATED';
    const WAITING_FOR_FILLING = 'WAITING_FOR_FILLING';
    const WAITING_FOR_CONFIRMATION = 'WAITING_FOR_CONFIRMATION';
    const WAITING_FOR_PAYMENT = 'WAITING_FOR_PAYMENT';
    const ACCEPTED = 'ACCEPTED';
    const PAID = 'PAID';
    const REJECTED = 'REJECTED';
    const RESIGN = 'RESIGN';
    const CANCELLED_BY_SHOP = 'CANCELLED_BY_SHOP';
    const CANCELLED = 'CANCELLED';

    const COMFINO_CREATED = 'COMFINO_CREATED';
    const COMFINO_WAITING_FOR_FILLING = 'COMFINO_WAITING_FOR_FILLING';
    const COMFINO_WAITING_FOR_CONFIRMATION = 'COMFINO_WAITING_FOR_CONFIRMATION';
    const COMFINO_WAITING_FOR_PAYMENT = 'COMFINO_WAITING_FOR_PAYMENT';
    const COMFINO_ACCEPTED = 'COMFINO_ACCEPTED';
    const COMFINO_PAID = 'COMFINO_PAID';
    const COMFINO_REJECTED = 'COMFINO_REJECTED';
    const COMFINO_RESIGN = 'COMFINO_RESIGN';
    const COMFINO_CANCELLED_BY_SHOP = 'COMFINO_CANCELLED_BY_SHOP';
    const COMFINO_CANCELLED = 'COMFINO_CANCELLED';

    const STATUSES = [
        self::CREATED => self::COMFINO_CREATED,
        self::WAITING_FOR_FILLING => self::COMFINO_WAITING_FOR_FILLING,
        self::WAITING_FOR_CONFIRMATION => self::COMFINO_WAITING_FOR_CONFIRMATION,
        self::WAITING_FOR_PAYMENT => self::COMFINO_WAITING_FOR_PAYMENT,
        self::ACCEPTED => self::COMFINO_ACCEPTED,
        self::REJECTED => self::COMFINO_REJECTED,
        self::PAID => self::COMFINO_PAID,
        self::RESIGN => self::COMFINO_RESIGN,
        self::CANCELLED_BY_SHOP => self::COMFINO_CANCELLED_BY_SHOP,
        self::CANCELLED => self::COMFINO_CANCELLED,
    ];

    /**
     * After setting notification status we want some statuses to change to internal PrestaShop statuses right away.
     */
    const CHANGE_STATUS_MAP = [
        self::ACCEPTED => 'PS_OS_WS_PAYMENT',
        self::CANCELLED => 'PS_OS_CANCELED',
        self::CANCELLED_BY_SHOP => 'PS_OS_CANCELED',
        self::REJECTED => 'PS_OS_CANCELED',
        self::RESIGN => 'PS_OS_CANCELED',
    ];

    const CUSTOM_ORDER_STATUSES = [
        self::COMFINO_CREATED => [
            'name' => 'Order created - waiting for payment (Comfino)',
            'name_pl' => 'Zamówienie utworzone - oczekiwanie na płatność (Comfino)',
            'color' => '#87b921',
            'paid' => false,
            'deleted' => false,
        ],
        self::COMFINO_WAITING_FOR_FILLING => [
            'name' => 'Waiting for form\'s filling (Comfino)',
            'name_pl' => 'Oczekiwanie na wypełnienie formularza (Comfino)',
            'color' => '#ffffff',
            'paid' => false,
            'deleted' => true,
        ],
        self::COMFINO_WAITING_FOR_CONFIRMATION => [
            'name' => 'Waiting for form\'s confirmation (Comfino)',
            'name_pl' => 'Oczekiwanie na zatwierdzenie formularza (Comfino)',
            'color' => '#ffffff',
            'paid' => false,
            'deleted' => true,
        ],
        self::COMFINO_WAITING_FOR_PAYMENT => [
            'name' => 'Waiting for payment (Comfino)',
            'name_pl' => 'Oczekiwanie na płatność (Comfino)',
            'color' => '#ffffff',
            'paid' => false,
            'deleted' => true,
        ],
        self::COMFINO_ACCEPTED => [
            'name' => 'Credit granted (Comfino)',
            'name_pl' => 'Kredyt udzielony (Comfino)',
            'color' => '#227b34',
            'paid' => true,
            'deleted' => false,
        ],
        self::COMFINO_PAID => [
            'name' => 'Paid (Comfino)',
            'name_pl' => 'Zapłacono (Comfino)',
            'color' => '#227b34',
            'paid' => true,
            'deleted' => true,
        ],
        self::COMFINO_REJECTED => [
            'name' => 'Credit rejected (Comfino)',
            'name_pl' => 'Wniosek kredytowy odrzucony (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
        self::COMFINO_RESIGN => [
            'name' => 'Resigned (Comfino)',
            'name_pl' => 'Odstąpiono (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
        self::COMFINO_CANCELLED_BY_SHOP => [
            'name' => 'Cancelled by shop (Comfino)',
            'name_pl' => 'Anulowano przez sklep (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
        self::COMFINO_CANCELLED => [
            'name' => 'Cancelled (Comfino)',
            'name_pl' => 'Anulowano (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
    ];

    const IGNORED_STATUSES = [
        self::WAITING_FOR_FILLING,
        self::WAITING_FOR_CONFIRMATION,
        self::WAITING_FOR_PAYMENT,
        self::PAID,
    ];

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
                        $product->name,
                        (int) ($product->getPrice() * 100),
                        (string) $product->id,
                        null,
                        null,
                        null,
                        $product->getCategories()
                    ),
                    1
                )
            ]
        );
    }

    public static function checkCartCurrency(\PaymentModule $module, \Cart $cart): bool
    {
        $currency_order = new \Currency($cart->id_currency);
        $currencies_module = $module->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id === $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $order_id
     * @param string $status
     * @return bool
     * @throws Exception
     */
    public static function processState($order_id, $status): bool
    {
        if (in_array($status, self::IGNORED_STATUSES, true)) {
            return true;
        }

        $order = new OrderCore($order_id);

        if (!ValidateCore::isLoadedObject($order)) {
            throw new Exception(sprintf('Order not found by id: %s', $order_id));
        }

        $internal_status_new = self::getState($status);

        if ($internal_status_new === 'PS_OS_ERROR') {
            return false;
        }

        $internal_status_current_id = (int) $order->getCurrentState();
        $internal_status_new_id = (int) Configuration::get($internal_status_new);

        if ($internal_status_new_id !== $internal_status_current_id) {
            $order->setCurrentState($internal_status_new_id);

            self::setSecondState($status, $order);
        }

        return true;
    }

    /**
     * @param string $state
     * @return string
     */
    private static function getState($state)
    {
        $state = Tools::strtoupper($state);

        if (array_key_exists($state, self::STATUSES)) {
            return self::STATUSES[$state];
        }

        return 'PS_OS_ERROR';
    }

    private static function setSecondState($status, OrderCore $order)
    {
        if (!array_key_exists($status, self::CHANGE_STATUS_MAP)) {
            return;
        }

        if (self::wasSecondStatusSetInHistory($status, $order)) {
            return;
        }

        $order->setCurrentState(Configuration::get(self::CHANGE_STATUS_MAP[$status]));
    }

    private static function wasSecondStatusSetInHistory($status, OrderCore $order)
    {
        $id_order_state = Configuration::get($status);

        foreach ($order->getHistory(0) as $historyElement) {
            if ($historyElement['id_order_state'] === $id_order_state) {
                return true;
            }
        }

        return false;
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
