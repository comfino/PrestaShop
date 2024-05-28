<?php

namespace Comfino\Common\Backend;

use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use Psr\Http\Message\ServerRequestInterface;

interface RestEndpointInterface
{
    public function getName(): string;

    public function getMethods(): array;

    public function getEndpointUrl(): string;

    /**
     * @throws InvalidEndpoint
     * @throws InvalidRequest
     */
    public function processRequest(ServerRequestInterface $serverRequest): ?array;
}
