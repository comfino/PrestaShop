<?php

namespace Comfino\Common\Backend\Payment\ProductTypeFilter;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Shop\Cart;

readonly class FilterByProductType implements ProductTypeFilterInterface
{
    /**
     * @param LoanTypeEnum[] $allowedProductTypes
     */
    public function __construct(private array $allowedProductTypes)
    {
    }

    public function getAllowedProductTypes(array $availableProductTypes, Cart $cart): array
    {
        return array_intersect($this->allowedProductTypes, $availableProductTypes);
    }
}
