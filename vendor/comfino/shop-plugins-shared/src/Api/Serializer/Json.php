<?php

namespace Comfino\Api\Serializer;

use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\SerializerInterface;

class Json implements SerializerInterface
{
    public function serialize(mixed $requestData): string
    {
        try {
            $serializedRequestBody = json_encode($requestData, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RequestValidationError("Invalid request data: {$e->getMessage()}", 0, $e);
        }

        return $serializedRequestBody;
    }

    public function unserialize(string $responseBody): mixed
    {
        try {
            $deserializedResponseBody = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ResponseValidationError("Invalid response data: {$e->getMessage()}", 0, $e);
        }

        return $deserializedResponseBody;
    }
}
