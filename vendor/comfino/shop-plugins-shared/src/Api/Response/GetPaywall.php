<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class GetPaywall extends Base
{
    /** @var string */
    public readonly string $paywallPage;

    /**
     * @inheritDoc
     */
    protected function processResponseBody(array|string|bool|null $deserializedResponseBody): void
    {
        if (!is_string($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: string expected.');
        }

        $this->paywallPage = $deserializedResponseBody;
    }
}
