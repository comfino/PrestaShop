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

namespace Comfino\Common\Backend\Factory;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Shop\Order\Cart;
use Comfino\Shop\Order\Cart\CartItemInterface;
use Comfino\Shop\Order\CustomerInterface;
use Comfino\Shop\Order\LoanParameters;
use Comfino\Shop\Order\Order;

final class OrderFactory
{
    /**
     * @param CartItemInterface[] $cartItems
     * @param LoanTypeEnum[]|null $allowedProductTypes
     */
    public function createOrder(
        string $orderId,
        int $orderTotal,
        int $deliveryCost,
        int $loanTerm,
        LoanTypeEnum $loanType,
        array $cartItems,
        CustomerInterface $customer,
        string $returnUrl,
        string $notificationUrl,
        ?array $allowedProductTypes = null,
        ?string $category = null
    ): Order {
        return new Order(
            $orderId,
            $returnUrl,
            new LoanParameters(
                $orderTotal,
                $loanTerm,
                $loanType,
                $allowedProductTypes
            ),
            new Cart($cartItems, $orderTotal, $deliveryCost, $category),
            $customer,
            $notificationUrl
        );
    }
}
