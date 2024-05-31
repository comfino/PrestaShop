<?php

namespace Comfino\Api;

use Comfino\Api\Exception\RequestValidationError;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * API request abstraction.
 */
abstract class Request
{
    /** @var SerializerInterface */
    protected $serializer;
    /** @var string */
    private $method;
    /** @var string */
    private $apiEndpointPath;
    /** @var array|null */
    private $requestParams;
    /** @var string|null */
    private $requestUri;
    /** @var string|null */
    private $requestBody;

    /**
     * @param \Comfino\Api\SerializerInterface $serializer
     */
    final public function setSerializer($serializer): self
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * Returns PSR-7 compatible HTTP request object.
     *
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface $streamFactory
     * @param string $apiHost
     * @param int $apiVersion
     * @return RequestInterface
     * @throws RequestValidationError
     */
    final public function getPsrRequest(
        $requestFactory,
        $streamFactory,
        $apiHost,
        $apiVersion
    ): RequestInterface {
        $this->requestUri = $this->getApiEndpointUri($apiHost, $apiVersion);

        if (empty($this->method)) {
            throw new RequestValidationError('Invalid request data: HTTP method undefined.', 0, null, $this->requestUri);
        }
        if (empty($this->apiEndpointPath)) {
            throw new RequestValidationError('Invalid request data: API endpoint path undefined.', 0, null, $this->requestUri);
        }

        $request = $requestFactory->createRequest($this->method, $this->requestUri);

        try {
            $this->requestBody = $this->serializeRequestBody();
        } catch (RequestValidationError $e) {
            $e->setUrl($this->requestUri);

            throw $e;
        }

        return $this->requestBody !== null ? $request->withBody($streamFactory->createStream($this->requestBody)) : $request;
    }

    /**
     * @return string|null
     */
    final public function getRequestUri(): ?string
    {
        return $this->requestUri;
    }

    /**
     * @return string|null
     */
    final public function getRequestBody(): ?string
    {
        return $this->requestBody;
    }

    /**
     * @return string
     * @throws RequestValidationError
     */
    final public function __toString(): string
    {
        return ($serializedBody = $this->serializeRequestBody()) !== null ? $serializedBody : '';
    }

    /**
     * @param string $method
     * @return void
     */
    final protected function setRequestMethod($method): void
    {
        $this->method = strtoupper(trim($method));
    }

    /**
     * @param string $apiEndpointPath
     * @return void
     */
    final protected function setApiEndpointPath($apiEndpointPath): void
    {
        $this->apiEndpointPath = trim($apiEndpointPath, " /\n\r\t\v\0");
    }

    /**
     * @param array $requestParams
     * @return void
     */
    final protected function setRequestParams($requestParams): void
    {
        $this->requestParams = $requestParams;
    }

    /**
     * @return string|null
     * @throws RequestValidationError
     */
    final protected function serializeRequestBody(): ?string
    {
        return ($body = $this->prepareRequestBody()) !== null ? $this->serializer->serialize($body) : null;
    }

    /**
     * @param string $apiHost
     * @param int $apiVersion
     * @return string
     */
    final protected function getApiEndpointUri($apiHost, $apiVersion): string
    {
        $uri = implode('/', [trim($apiHost, " /\n\r\t\v\0"), "v$apiVersion", $this->apiEndpointPath]);

        if (!empty($this->requestParams)) {
            $uri .= ('?' . http_build_query($this->requestParams));
        }

        return $uri;
    }

    /**
     * Converts API request object to the array which is ready for serialization.
     *
     * @return array|null
     */
    abstract protected function prepareRequestBody(): ?array;
}