<?php

namespace Comfino\Common\Backend;

use Psr\Http\Message\ServerRequestInterface;

abstract class RestEndpoint implements RestEndpointInterface
{
    protected array $methods;

    public function __construct(private readonly string $name, private readonly string $endpointUrl)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getEndpointUrl(): string
    {
        return $this->endpointUrl;
    }

    protected function endpointPathMatch(ServerRequestInterface $serverRequest): bool
    {
        return $serverRequest->getUri()->getPath() === parse_url($this->endpointUrl, PHP_URL_PATH) &&
            in_array($serverRequest->getMethod(), $this->methods, true);
    }
}
