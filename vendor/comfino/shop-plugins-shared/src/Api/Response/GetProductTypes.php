<?php

namespace Comfino\Api\Response;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Exception\ResponseValidationError;

class GetProductTypes extends Base
{
    /** @var LoanTypeEnum[] */
    public readonly array $productTypes;
    /** @var string[] */
    public readonly array $productTypesWithNames;

    /**
     * @inheritDoc
     */
    protected function processResponseBody(array|string|bool|null $deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->productTypesWithNames = $deserializedResponseBody;
        $this->productTypes = array_map(
            static fn (string $productType): LoanTypeEnum => LoanTypeEnum::from($productType, false),
            array_keys($deserializedResponseBody)
        );
    }
}
