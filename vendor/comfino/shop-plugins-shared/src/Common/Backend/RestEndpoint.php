<?php

namespace Comfino\Common\Backend;

use Psr\Http\Message\ServerRequestInterface;

abstract class RestEndpoint implements RestEndpointInterface
{
    /**
     * @readonly
     * @var string
     */
    private $name;
    /**
     * @readonly
     * @var string
     */
    private $endpointUrl;
    /**
     * @var mixed[]
     */
    protected $methods;

    public function __construct(string $name, string $endpointUrl)
    {
        $this->name = $name;
        $this->endpointUrl = $endpointUrl;
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

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $serverRequest
     * @param string|null $endpointName
     */
    protected function endpointPathMatch($serverRequest, $endpointName = null): bool
    {
        if ($endpointName !== null && $endpointName === $this->name && in_array($serverRequest->getMethod(), $this->methods, true)) {
            return true;
        }

        return (string) $serverRequest->getUri() === $this->endpointUrl && in_array($serverRequest->getMethod(), $this->methods, true);
    }
}
