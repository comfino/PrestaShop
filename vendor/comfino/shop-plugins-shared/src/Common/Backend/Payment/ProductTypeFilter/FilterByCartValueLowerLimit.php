<?php

namespace Comfino\Common\Backend\Payment\ProductTypeFilter;

use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Shop\Cart;

readonly class FilterByCartValueLowerLimit implements ProductTypeFilterInterface
{
    /**
     * @param int[] $cartValueLimitsByProductType ['PRODUCT_TYPE' => cart_value_limit]
     */
    public function __construct(private array $cartValueLimitsByProductType)
    {
    }

    public function getAllowedProductTypes(array $availableProductTypes, Cart $cart): array
    {
        $allowedProductTypes = [];

        foreach ($availableProductTypes as $productType) {
            if (array_key_exists((string) $productType, $this->cartValueLimitsByProductType)) {
                if ($cart->getTotalValue() >= $this->cartValueLimitsByProductType[(string) $productType]) {
                    $allowedProductTypes[] = $productType;
                }
            } else {
                $allowedProductTypes[] = $productType;
            }
        }

        return $allowedProductTypes;
    }
}
