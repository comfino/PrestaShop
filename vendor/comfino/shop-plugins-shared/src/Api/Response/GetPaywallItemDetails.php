<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class GetPaywallItemDetails extends Base
{
    /** @var string */
    public readonly string $productDetails;
    /** @var string */
    public readonly string $listItemData;

    /**
     * @inheritDoc
     */
    protected function processResponseBody(array|string|bool|null $deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->productDetails = $deserializedResponseBody['productDetails'];
        $this->listItemData = $deserializedResponseBody['listItemData'];
    }
}
