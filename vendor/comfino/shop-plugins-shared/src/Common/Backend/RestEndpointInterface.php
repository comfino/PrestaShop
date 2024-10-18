<?php

namespace Comfino\Common\Backend;

use Comfino\Api\SerializerInterface;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use ComfinoExternal\Psr\Http\Message\ServerRequestInterface;

interface RestEndpointInterface
{
    public function getName(): string;

    public function getMethods(): array;

    public function getEndpointUrl(): string;

    public function setSerializer(SerializerInterface $serializer): void;

    /**
     * @throws InvalidEndpoint
     * @throws InvalidRequest
     */
    public function processRequest(ServerRequestInterface $serverRequest, ?string $endpointName = null): ?array;
}
