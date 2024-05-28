<?php

namespace Comfino\Extended\Api\Request;

use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Request;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;

/**
 * Shop plugin error reporting request.
 */
class ReportShopPluginError extends Request
{
    public function __construct(private readonly ShopPluginError $shopPluginError, private readonly string $hashKey)
    {
        $this->setRequestMethod('POST');
        $this->setApiEndpointPath('log-plugin-error');
    }

    protected function prepareRequestBody(): ?array
    {
        $errorDetailsArray = [
            'host' => $this->shopPluginError->host,
            'platform' => $this->shopPluginError->platform,
            'environment' => $this->shopPluginError->environment,
            'error_code' => $this->shopPluginError->errorCode,
            'error_message' => $this->shopPluginError->errorMessage,
            'api_request_url' => $this->shopPluginError->apiRequestUrl,
            'api_request' => $this->shopPluginError->apiRequest,
            'api_response' => $this->shopPluginError->apiResponse,
            'stack_trace' => $this->shopPluginError->stackTrace,
        ];

        if (($errorDetails = gzcompress($this->serializer->serialize($errorDetailsArray), 9)) === false) {
            throw new RequestValidationError('Error report preparation failed.');
        }

        return [
            'error_details' => base64_encode($errorDetails),
            'hash' => hash_hmac('sha256', $errorDetails, $this->hashKey),
        ];
    }
}
