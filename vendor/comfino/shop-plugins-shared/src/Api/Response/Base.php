<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Api\Response;
use Comfino\Api\SerializerInterface;
use Psr\Http\Message\ResponseInterface;

class Base extends Response
{
    /**
     * @param ResponseInterface $response
     * @param SerializerInterface $serializer
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     */
    public function __construct(ResponseInterface $response, SerializerInterface $serializer)
    {
        $this->initFromPsrResponse($response, $serializer);
    }

    /**
     * @inheritDoc
     */
    protected function processResponseBody(array|string|bool|null $deserializedResponseBody): void
    {
    }
}
