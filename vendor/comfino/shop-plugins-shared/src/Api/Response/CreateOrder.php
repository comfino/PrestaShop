<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class CreateOrder extends Base
{
    /** @var string */
    public readonly string $status;
    /** @var string */
    public readonly string $externalId;
    /** @var string */
    public readonly string $applicationUrl;

    protected function processResponseBody(array|string|bool|null $deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->status = $deserializedResponseBody['status'];
        $this->externalId = $deserializedResponseBody['externalId'];
        $this->applicationUrl = $deserializedResponseBody['applicationUrl'];
    }
}
