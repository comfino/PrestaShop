<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class IsShopAccountActive extends Base
{
    /** @var bool */
    public readonly bool $isActive;

    /**
     * @inheritDoc
     */
    protected function processResponseBody(array|string|bool|null $deserializedResponseBody): void
    {
        if (!is_bool($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: bool expected.');
        }

        $this->isActive = $deserializedResponseBody;
    }
}
