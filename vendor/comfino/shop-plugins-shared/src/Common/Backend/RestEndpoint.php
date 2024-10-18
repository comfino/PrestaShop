<?php

namespace Comfino\Common\Backend;

use Comfino\Api\SerializerInterface;
use ComfinoExternal\Psr\Http\Message\ServerRequestInterface;

abstract class RestEndpoint implements RestEndpointInterface
{
    protected array $methods;
    protected ?SerializerInterface $serializer = null;

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

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    protected function endpointPathMatch(ServerRequestInterface $serverRequest, ?string $endpointName = null): bool
    {
        $requestMethod = strtoupper($serverRequest->getMethod());

        if ($endpointName !== null && $endpointName === $this->name && in_array($requestMethod, $this->methods, true)) {
            return true;
        }

        return (string) $serverRequest->getUri() === $this->endpointUrl && in_array($requestMethod, $this->methods, true);
    }

    protected function getParsedRequestBody(ServerRequestInterface $serverRequest): array|string|null
    {
        $contentType = $serverRequest->hasHeader('Content-Type') ? $serverRequest->getHeader('Content-Type')[0] : '';
        $requestPayload = $serverRequest->getBody()->getContents();

        $serverRequest->getBody()->rewind();

        if ($contentType === 'application/json') {
            if ($this->serializer !== null) {
                return $this->serializer->unserialize($requestPayload);
            }

            return json_decode($requestPayload, true);
        }

        if (strtoupper($serverRequest->getMethod()) === 'POST') {
            return $serverRequest->getParsedBody();
        }

        return $requestPayload;
    }
}
