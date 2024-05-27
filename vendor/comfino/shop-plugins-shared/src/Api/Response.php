<?php

namespace Comfino\Api;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Psr\Http\Message\ResponseInterface;

abstract class Response
{
    /**
     * Extracts API response data from input PSR-7 compatible HTTP response object.
     *
     * @param ResponseInterface $response
     * @param SerializerInterface $serializer
     * @return Response
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     */
    final public function initFromPsrResponse($response, $serializer): self
    {
        $response->getBody()->rewind();
        $responseBody = $response->getBody()->getContents();

        if (strpos($response->getHeader('Content-Type')[0], 'application/json') !== false) {
            try {
                $deserializedResponseBody = $this->deserializeResponseBody($responseBody, $serializer);
            } catch (ResponseValidationError $e) {
                $e->setUrl($response->getBody()->getMetadata('uri'));
                $e->setResponseBody($responseBody);

                throw $e;
            }
        } else {
            $deserializedResponseBody = $responseBody;
        }

        if ($response->getStatusCode() >= 500) {
            throw new ServiceUnavailable(
                "Comfino API service is unavailable: {$response->getReasonPhrase()} [{$response->getStatusCode()}]",
                0,
                null,
                $response->getBody()->getMetadata('uri'),
                '',
                $responseBody
            );
        }

        if ($response->getStatusCode() >= 400) {
            switch ($response->getStatusCode()) {
                case 400:
                    throw new RequestValidationError(
                        $this->getErrorMessage(
                            $deserializedResponseBody,
                            "Invalid request data: {$response->getReasonPhrase()} [{$response->getStatusCode()}]"
                        ),
                        0,
                        null,
                        $response->getBody()->getMetadata('uri')
                    );

                case 401:
                    throw new AuthorizationError(
                        $this->getErrorMessage($deserializedResponseBody, "Invalid credentials: {$response->getReasonPhrase()} [{$response->getStatusCode()}]"),
                        0,
                        null,
                        $response->getBody()->getMetadata('uri')
                    );

                case 402:
                case 403:
                case 404:
                case 405:
                    throw new AccessDenied(
                        $this->getErrorMessage(
                            $deserializedResponseBody,
                            "Access denied: {$response->getReasonPhrase()} [{$response->getStatusCode()}]"
                        ),
                        0,
                        null,
                        $response->getBody()->getMetadata('uri')
                    );

                default:
                    throw new RequestValidationError(
                        "Invalid request data: {$response->getReasonPhrase()} [{$response->getStatusCode()}]",
                        0,
                        null,
                        $response->getBody()->getMetadata('uri')
                    );
            }
        }

        if (($errorMessage = $this->getErrorMessage($deserializedResponseBody)) !== null) {
            throw new RequestValidationError(
                $errorMessage,
                0,
                null,
                $response->getBody()->getMetadata('uri')
            );
        }

        try {
            $this->processResponseBody($deserializedResponseBody);
        } catch (ResponseValidationError $e) {
            $e->setUrl($response->getBody()->getMetadata('uri'));
            $e->setResponseBody($responseBody);

            throw $e;
        }

        return $this;
    }

    /**
     * Fills response object properties with data from deserialized API response array.
     *
     * @throws ResponseValidationError
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    abstract protected function processResponseBody($deserializedResponseBody): void;

    /**
     * @throws ResponseValidationError
     * @return mixed[]|bool|string|null
     */
    private function deserializeResponseBody(string $responseBody, SerializerInterface $serializer)
    {
        return !empty($responseBody) ? $serializer->unserialize($responseBody) : null;
    }

    /**
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    private function getErrorMessage($deserializedResponseBody, ?string $defaultMessage = null): ?string
    {
        if (!is_array($deserializedResponseBody)) {
            return null;
        }

        $errorMessages = [];

        if (isset($deserializedResponseBody['errors'])) {
            $errorMessages = array_map(
                static function (string $errorFieldName, string $errorMessage) {
                    return "$errorFieldName: $errorMessage";
                },
                array_keys($deserializedResponseBody['errors']),
                array_values($deserializedResponseBody['errors'])
            );
        } elseif (isset($deserializedResponseBody['message'])) {
            $errorMessages = [$deserializedResponseBody['message']];
        }

        return count($errorMessages) ? implode("\n", $errorMessages) : $defaultMessage;
    }
}
