<?php

namespace Comfino\Api\Dto\Order;

use Comfino\Api\Dto\Order\Cart\CartItem;

readonly class Cart
{
    /** @var int */
    public int $totalAmount;
    /** @var int */
    public int $deliveryCost;
    /** @var string|null */
    public ?string $category;
    /** @var CartItem[] */
    public array $products;

    /**
     * @param int $totalAmount
     * @param int $deliveryCost
     * @param string|null $category
     * @param CartItem[] $products
     */
    public function __construct(int $totalAmount, int $deliveryCost, ?string $category, array $products)
    {
        $this->totalAmount = $totalAmount;
        $this->deliveryCost = $deliveryCost;
        $this->category = $category;
        $this->products = $products;
    }
}
