<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class GetPaywallFragments extends Base
{
    /** @var array */
    public readonly array $paywallFragments;

    /**
     * @inheritDoc
     */
    protected function processResponseBody(array|string|bool|null $deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->paywallFragments = $deserializedResponseBody;
    }
}
