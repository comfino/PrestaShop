<?php

namespace Comfino\Extended\Api\Dto\Plugin;

final class ShopPluginError
{
    public string $host;
    public string $platform;
    public array $environment;
    public string $errorCode;
    public string $errorMessage;
    public ?string $apiRequestUrl;
    public ?string $apiRequest;
    public ?string $apiResponse;
    public ?string $stackTrace;

    public function __construct(
        string $host,
        string $platform,
        array $environment,
        string $errorCode,
        string $errorMessage,
        ?string $apiRequestUrl = null,
        ?string $apiRequest = null,
        ?string $apiResponse = null,
        ?string $stackTrace = null
    ) {
        $this->host = $host;
        $this->platform = $platform;
        $this->environment = $environment;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->apiRequestUrl = $apiRequestUrl;
        $this->apiRequest = $apiRequest;
        $this->apiResponse = $apiResponse;
        $this->stackTrace = $stackTrace;
    }
}
