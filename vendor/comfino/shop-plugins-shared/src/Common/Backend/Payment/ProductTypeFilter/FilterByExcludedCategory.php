<?php

namespace Comfino\Common\Backend\Payment\ProductTypeFilter;

use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Shop\Cart;
use Comfino\Common\Shop\Product\CategoryFilter;

readonly class FilterByExcludedCategory implements ProductTypeFilterInterface
{
    /**
     * @param int[][] $excludedCategoryIdsByProductType ['PRODUCT_TYPE' => [excluded_category_ids]]
     */
    public function __construct(private CategoryFilter $categoryFilter, private array $excludedCategoryIdsByProductType)
    {
    }

    public function getAllowedProductTypes(array $availableProductTypes, Cart $cart): array
    {
        $allowedProductTypes = [];

        foreach ($availableProductTypes as $productType) {
            if (array_key_exists((string) $productType, $this->excludedCategoryIdsByProductType)) {
                if ($this->categoryFilter->isCartValid($cart, $this->excludedCategoryIdsByProductType[(string) $productType])) {
                    $allowedProductTypes[] = $productType;
                }
            } else {
                $allowedProductTypes[] = $productType;
            }
        }

        return $allowedProductTypes;
    }
}
