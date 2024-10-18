<?php

namespace Comfino\Api;

use Comfino\Api\Exception\RequestValidationError;
use ComfinoExternal\Psr\Http\Message\RequestFactoryInterface;
use ComfinoExternal\Psr\Http\Message\RequestInterface;
use ComfinoExternal\Psr\Http\Message\StreamFactoryInterface;

/**
 * API request abstraction.
 */
abstract class Request
{
    /** @var SerializerInterface */
    protected SerializerInterface $serializer;
    /** @var string */
    private string $method;
    /** @var string */
    private string $apiEndpointPath;
    /** @var string[]|null */
    private ?array $requestHeaders = null;
    /** @var string[]|null */
    private ?array $requestParams = null;
    /** @var string|null */
    private ?string $requestUri = null;
    /** @var string|null */
    private ?string $requestBody = null;

    final public function setSerializer(SerializerInterface $serializer): self
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
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $apiHost,
        int $apiVersion
    ): RequestInterface {
        $this->requestUri = $this->getApiEndpointUri($apiHost, $apiVersion);

        if (empty($this->method)) {
            throw new RequestValidationError('Invalid request data: HTTP method undefined.', 0, null, $this->requestUri);
        }
        if (empty($this->apiEndpointPath)) {
            throw new RequestValidationError('Invalid request data: API endpoint path undefined.', 0, null, $this->requestUri);
        }

        $request = $requestFactory->createRequest($this->method, $this->requestUri);

        if (!empty($requestHeaders = $this->getRequestHeaders())) {
            foreach ($requestHeaders as $headerName => $headerValue) {
                $request = $request->withHeader($headerName, $headerValue);
            }
        }

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
    final protected function setRequestMethod(string $method): void
    {
        $this->method = strtoupper(trim($method));
    }

    /**
     * @param string $apiEndpointPath
     * @return void
     */
    final protected function setApiEndpointPath(string $apiEndpointPath): void
    {
        $this->apiEndpointPath = trim($apiEndpointPath, " /\n\r\t\v\0");
    }

    /**
     * @param string[] $requestHeaders
     * @return void
     */
    final protected function setRequestHeaders(array $requestHeaders): void
    {
        $this->requestHeaders = $requestHeaders;
    }

    /**
     * @param string[] $requestParams
     * @return void
     */
    final protected function setRequestParams(array $requestParams): void
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
    final protected function getApiEndpointUri(string $apiHost, int $apiVersion): string
    {
        $uri = implode('/', [trim($apiHost, " /\n\r\t\v\0"), "v$apiVersion", $this->apiEndpointPath]);

        if (!empty($this->requestParams)) {
            $uri .= ('?' . http_build_query($this->requestParams));
        }

        return $uri;
    }

    final protected function getRequestHeaders(): ?array
    {
        return $this->requestHeaders;
    }

    /**
     * Converts API request object to the array which is ready for serialization.
     *
     * @return array|null
     */
    abstract protected function prepareRequestBody(): ?array;
}
