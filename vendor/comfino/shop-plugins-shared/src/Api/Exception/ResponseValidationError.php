<?php

namespace Comfino\Api\Exception;

class ResponseValidationError extends \RuntimeException
{
    /** @var string */
    private string $url;
    /** @var string */
    private string $requestBody;
    /** @var string */
    private string $responseBody;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, string $url = '', string $requestBody = '', string $responseBody = '')
    {
        parent::__construct($message, $code, $previous);

        $this->url = $url;
        $this->requestBody = $requestBody;
        $this->responseBody = $responseBody;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getRequestBody(): string
    {
        return $this->requestBody;
    }

    public function setRequestBody(string $requestBody): void
    {
        $this->requestBody = $requestBody;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    public function setResponseBody(string $responseBody): void
    {
        $this->responseBody = $responseBody;
    }
}
