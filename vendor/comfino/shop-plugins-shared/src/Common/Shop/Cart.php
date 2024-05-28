<?php

namespace Comfino\Common\Shop;

use Comfino\Shop\Order\Cart\CartItemInterface;

class Cart
{
    /** @var int[]|null  */
    private ?array $cartCategoryIds = null;

    /**
     * @param CartItemInterface[] $cartItems
     */
    public function __construct(private readonly int $totalValue, private readonly int $deliveryCost, private readonly array $cartItems)
    {
    }

    public function getTotalValue(): int
    {
        return $this->totalValue;
    }

    public function getDeliveryCost(): int
    {
        return $this->deliveryCost;
    }

    /**
     * @return CartItemInterface[]
     */
    public function getCartItems(): array
    {
        return $this->cartItems;
    }

    /**
     * @return int[]
     */
    public function getCartCategoryIds(): array
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

        return ($this->cartCategoryIds = array_unique(array_merge([], ...$categoryIds), SORT_NUMERIC));
    }
}
