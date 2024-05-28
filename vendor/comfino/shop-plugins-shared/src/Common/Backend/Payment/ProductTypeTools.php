<?php

namespace Comfino\Common\Backend\Payment;

use Comfino\Api\Dto\Payment\LoanTypeEnum;

final class ProductTypeTools
{
    /**
     * @param string[] $productTypes
     * @return LoanTypeEnum[]
     */
    public static function getAsEnums(array $productTypes): array
    {
        return array_map(
            static fn (string $productType): LoanTypeEnum => LoanTypeEnum::from($productType),
            $productTypes
        );
    }
}
