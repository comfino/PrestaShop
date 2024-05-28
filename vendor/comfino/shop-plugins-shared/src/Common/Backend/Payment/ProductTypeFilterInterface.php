<?php

namespace Comfino\Common\Backend\Payment;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Shop\Cart;

interface ProductTypeFilterInterface
{
    /**
     * @param LoanTypeEnum[] $availableProductTypes
     * @return LoanTypeEnum[]
     */
    public function getAllowedProductTypes(array $availableProductTypes, Cart $cart): array;
}
