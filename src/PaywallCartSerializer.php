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

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Api/Cart.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Order/Cart.php';

use Comfino\Order\Cart as ShopCart;

/**
 * Produces the "cart" payload carried by the paywall bootstrap config, in the same shape the order creation
 * request sends to the API, so the paywall renders offers for exactly the cart the order will be created from.
 */
class PaywallCartSerializer
{
    /**
     * @param ShopCart $shopCart
     *
     * @return array
     */
    public static function toArray(ShopCart $shopCart)
    {
        return Api::getCartAsArray(
            new Api\Cart(
                $shopCart->getCartItems(),
                $shopCart->getTotalValue(),
                $shopCart->getDeliveryCost(),
                $shopCart->getDeliveryNetCost(),
                $shopCart->getDeliveryTaxRate(),
                $shopCart->getDeliveryTaxValue()
            )
        );
    }
}