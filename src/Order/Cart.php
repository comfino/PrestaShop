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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Order/Cart/CartItemInterface.php';

use Comfino\Order\Cart\CartItemInterface;

class Cart
{
    /**
     * @readonly
     *
     * @var int
     */
    private $totalValue;

    /**
     * @readonly
     *
     * @var int|null
     */
    private $totalNetValue;

    /**
     * @readonly
     *
     * @var int|null
     */
    private $totalTaxValue;

    /**
     * @readonly
     *
     * @var int
     */
    private $deliveryCost;

    /**
     * @readonly
     *
     * @var int|null
     */
    private $deliveryNetCost;

    /**
     * @readonly
     *
     * @var int|null
     */
    private $deliveryTaxRate;

    /**
     * @readonly
     *
     * @var int|null
     */
    private $deliveryTaxValue;

    /**
     * @var CartItemInterface[]
     *
     * @readonly
     */
    private $cartItems;

    /** @var int[]|null */
    private $cartCategoryIds;

    /**
     * @param int $totalValue
     * @param int|null $totalNetValue
     * @param int|null $totalTaxValue
     * @param int $deliveryCost
     * @param int|null $deliveryNetCost
     * @param int|null $deliveryTaxRate
     * @param int|null $deliveryTaxValue
     * @param CartItemInterface[] $cartItems
     */
    public function __construct(
        $totalValue,
        $totalNetValue,
        $totalTaxValue,
        $deliveryCost,
        $deliveryNetCost,
        $deliveryTaxRate,
        $deliveryTaxValue,
        array $cartItems
    ) {
        $this->totalValue = $totalValue;
        $this->totalNetValue = $totalNetValue;
        $this->totalTaxValue = $totalTaxValue;
        $this->deliveryCost = $deliveryCost;
        $this->deliveryNetCost = $deliveryNetCost;
        $this->deliveryTaxRate = $deliveryTaxRate;
        $this->deliveryTaxValue = $deliveryTaxValue;
        $this->cartItems = $cartItems;
    }

    /**
     * @return int
     */
    public function getTotalValue()
    {
        return $this->totalValue;
    }

    /**
     * @return int|null
     */
    public function getTotalNetValue()
    {
        return $this->totalNetValue;
    }

    /**
     * @return int|null
     */
    public function getTotalTaxValue()
    {
        return $this->totalTaxValue;
    }

    /**
     * @return int
     */
    public function getDeliveryCost()
    {
        return $this->deliveryCost;
    }

    /**
     * @return int|null
     */
    public function getDeliveryNetCost()
    {
        return $this->deliveryNetCost;
    }

    /**
     * @return int|null
     */
    public function getDeliveryTaxRate()
    {
        return $this->deliveryTaxRate;
    }

    /**
     * @return int|null
     */
    public function getDeliveryTaxValue()
    {
        return $this->deliveryTaxValue;
    }

    /**
     * @return CartItemInterface[]
     */
    public function getCartItems()
    {
        return $this->cartItems;
    }

    /**
     * @return int[]
     */
    public function getCartCategoryIds()
    {
        if ($this->cartCategoryIds !== null) {
            return $this->cartCategoryIds;
        }

        $categoryIds = [];

        foreach ($this->cartItems as $cartItem) {
            if (($productCategoryIds = $cartItem->getProduct()->getCategoryIds()) !== null) {
                $categoryIds[] = $productCategoryIds;
            }
        }

        return $this->cartCategoryIds = array_unique(array_merge([], ...$categoryIds), SORT_NUMERIC);
    }

    /**
     * @param bool $withNulls
     */
    public function getAsArray($withNulls = true)
    {
        $cart = [
            'totalAmount' => $this->totalValue,
            'deliveryCost' => $this->deliveryCost,
            'deliveryNetCost' => $this->deliveryNetCost,
            'deliveryCostVatRate' => $this->deliveryTaxRate,
            'deliveryCostVatAmount' => $this->deliveryTaxValue,
            'products' => array_map(
                static function (CartItemInterface $cartItem) use ($withNulls) {
                    $product = [
                        'name' => $cartItem->getProduct()->getName(),
                        'quantity' => $cartItem->getQuantity(),
                        'price' => $cartItem->getProduct()->getPrice(),
                        'netPrice' => $cartItem->getProduct()->getNetPrice(),
                        'vatRate' => $cartItem->getProduct()->getTaxRate(),
                        'vatAmount' => $cartItem->getProduct()->getTaxValue(),
                        'externalId' => $cartItem->getProduct()->getId(),
                        'category' => $cartItem->getProduct()->getCategory(),
                        'ean' => $cartItem->getProduct()->getEan(),
                        'photoUrl' => $cartItem->getProduct()->getPhotoUrl(),
                        'categoryIds' => $cartItem->getProduct()->getCategoryIds(),
                    ];

                    return $withNulls ? $product : array_filter($product, static function ($productFieldValue) {
                        return $productFieldValue !== null;
                    });
                },
                $this->cartItems
            ),
        ];

        return $withNulls ? $cart : array_filter($cart, static function ($cartFieldValue) {
            return $cartFieldValue !== null;
        });
    }
}
