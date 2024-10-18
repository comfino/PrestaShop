<?php

namespace Comfino\Common\Shop\Product;

use Comfino\Common\Shop\Cart;

readonly class CategoryFilter
{
    public function __construct(private CategoryTree $categoryTree)
    {
    }

    /**
     * @param int[] $excludedCategoryIds
     */
    public function isCategoryAvailable(int $categoryId, array $excludedCategoryIds): bool
    {
        if (in_array($categoryId, $excludedCategoryIds, true)) {
            return false;
        }

        if (($categoryNode = $this->categoryTree->getNodeById($categoryId)) === null) {
            return false;
        }

        foreach ($excludedCategoryIds as $excludedCategoryId) {
            if (($excludedCategory = $this->categoryTree->getNodeById($excludedCategoryId)) === null) {
                continue;
            }

            if ($categoryNode->isDescendantOf($excludedCategory)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int[] $excludedCategoryIds
     */
    public function isCartValid(Cart $cart, array $excludedCategoryIds): bool
    {
        if (empty($excludedCategoryIds || empty($cart->getCartItems()))) {
            return true;
        }

        $cartCategoryIds = $cart->getCartCategoryIds();

        if (count(array_intersect($cartCategoryIds, $excludedCategoryIds))) {
            return false;
        }

        foreach ($cartCategoryIds as $categoryId) {
            if (!$this->isCategoryAvailable($categoryId, $excludedCategoryIds)) {
                return false;
            }
        }

        return true;
    }
}
