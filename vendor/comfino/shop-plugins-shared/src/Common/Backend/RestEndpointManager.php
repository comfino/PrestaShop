<?php

namespace Comfino\Common\Backend;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\SerializerInterface;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

final class RestEndpointManager
{
    private static ?self $instance = null;

    /** @var RestEndpointInterface[] */
    protected array $registeredEndpoints = [];

    /**
     * @param string[] $apiKeys
     */
    public static function getInstance(
        string $platformName,
        string $platformVersion,
        string $pluginVersion,
        array $apiKeys,
        ServerRequestFactoryInterface $serverRequestFactory,
        StreamFactoryInterface $streamFactory,
        UriFactoryInterface $uriFactory,
        ResponseFactoryInterface $responseFactory,
        SerializerInterface $serializer
    ): self {
        if (self::$instance === null) {
            self::$instance = new self(
                $platformName,
                $platformVersion,
                $pluginVersion,
                $apiKeys,
                $serverRequestFactory,
                $streamFactory,
                $uriFactory,
                $responseFactory,
                $serializer
            );
        }

        return self::$instance;
    }

    /**
     * @param string[] $apiKeys
     */
    private function __construct(
        protected readonly string $platformName,
        protected readonly string $platformVersion,
        protected readonly string $pluginVersion,
        protected readonly array $apiKeys,
        protected readonly ServerRequestFactoryInterface $serverRequestFactory,
        protected readonly StreamFactoryInterface $streamFactory,
        protected readonly UriFactoryInterface $uriFactory,
        protected readonly ResponseFactoryInterface $responseFactory,
        protected readonly SerializerInterface $serializer
    ) {
    }

    public function registerEndpoint(RestEndpointInterface $endpoint): void
    {
        $this->registeredEndpoints[$endpoint->getName()] = $endpoint;
    }

    public function getEndpointByName(string $name): ?RestEndpointInterface
    {
        return $this->registeredEndpoints[$name] ?? null;
    }

    public function getRegisteredEndpoints(): array
    {
        $endpoints = [];

        foreach ($this->registeredEndpoints as $endpoint) {
            $endpoints[(new \ReflectionClass($endpoint))->getShortName()] = [
                'url' => $endpoint->getEndpointUrl(),
                'methods' => $endpoint->getMethods(),
            ];
        }

        return $endpoints;
    }

    public function processRequest(): ResponseInterface
    {
        $serverRequest = $this->getServerRequest();

        try {
            $this->verifyRequest($serverRequest);
        } catch (AuthorizationError $e) {
            return $this->getPreparedResponse($this->responseFactory->createResponse(401, $e->getMessage()));
        } catch (AccessDenied $e) {
            return $this->getPreparedResponse($this->responseFactory->createResponse(403, $e->getMessage()));
        }

        foreach ($this->registeredEndpoints as $endpoint) {
            try {
                $responseBody = $endpoint->processRequest($serverRequest);

                switch ($serverRequest->getMethod()) {
                    case 'GET':
                        return $this->getPreparedResponse(
                            $this->responseFactory->createResponse(200, 'OK'),
                            $responseBody
                        );

                    case 'POST':
                        return $this->getPreparedResponse(
                            $this->responseFactory->createResponse(201, 'Created'),
                            $responseBody
                        );

                    case 'PUT':
                    case 'PATCH':
                    case 'DELETE':
                        return empty($responseBody)
                            ? $this->getPreparedResponse($this->responseFactory->createResponse(204, 'No content'))
                            : $this->getPreparedResponse($this->responseFactory->createResponse(200, 'OK'), $responseBody);
                }
            } catch (InvalidEndpoint) {
                continue;
            } catch (InvalidRequest $e) {
                return $this->getPreparedResponse(
                    $this->responseFactory->createResponse(400, $e->getMessage()),
                    ['error' => $e->getMessage()]
                );
            }
        }

        return $this->getPreparedResponse($this->responseFactory->createResponse(404, 'Endpoint not found.'));
    }

    protected function getServerRequest(): ServerRequestInterface
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (array_key_exists('HTTPS', $_SERVER) && 'off' !== $_SERVER['HTTPS']) {
            $scheme = 'https://';
        } else {
            $scheme = 'http://';
        }

        if (array_key_exists('HTTP_HOST', $_SERVER)) {
            $host = $_SERVER['HTTP_HOST'];
        } elseif (array_key_exists('SERVER_NAME', $_SERVER)) {
            $host = $_SERVER['SERVER_NAME'];

            if (array_key_exists('SERVER_PORT', $_SERVER)) {
                $host .= (':' . $_SERVER['SERVER_PORT']);
            }
        } else {
            $host = 'localhost';
        }

        if (array_key_exists('REQUEST_URI', $_SERVER)) {
            $target = $_SERVER['REQUEST_URI'];
        } elseif (array_key_exists('PHP_SELF', $_SERVER)) {
            $target = $_SERVER['PHP_SELF'];

            if (array_key_exists('QUERY_STRING', $_SERVER)) {
                $target .= ('?' . $_SERVER['QUERY_STRING']);
            }
        } else {
            $target = '/';
        }

        $serverRequest = $this->serverRequestFactory->createServerRequest(
            $requestMethod,
            $this->uriFactory->createUri($scheme . $host . $target),
            $_SERVER
        );

        if ($requestMethod === 'POST' || $requestMethod === 'PUT' || $requestMethod === 'PATCH') {
            $input = fopen('php://input', 'rb');
            $resource = fopen('php://temp', 'r+b');

            stream_copy_to_stream($input, $resource);
            rewind($resource);

            $bodyStream = $this->streamFactory->createStreamFromResource($resource);

            if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
                rewind($input);

                if (!empty($body = stream_get_contents($input))) {
                    $parsedBody = $this->serializer->unserialize($body);
                }
            } else {
                $parsedBody = $_POST;
            }

            fclose($input);
        }

        if (isset($bodyStream)) {
            return isset($parsedBody)
                ? $serverRequest->withQueryParams($_GET)->withBody($bodyStream)->withParsedBody($parsedBody)
                : $serverRequest->withQueryParams($_GET)->withBody($bodyStream);
        }

        return $serverRequest->withQueryParams($_GET);
    }

    /**
     * @throws AuthorizationError
     * @throws AccessDenied
     */
    protected function verifyRequest(ServerRequestInterface $request): void
    {
        $crSignature = $request->hasHeader('CR-Signature') ? $request->getHeader('CR-Signature')[0] : null;

        if (empty($crSignature) && $request->hasHeader('X-CR-Signature')) {
            $crSignature = $request->getHeader('X-CR-Signature')[0] ?? null;
        }

        if (empty($crSignature)) {
            throw new AuthorizationError('Unauthorized request.');
        }

        $requestAuthorized = false;

        if ($request->getMethod() === 'GET') {
            if (!isset($request->getQueryParams()['vkey'])) {
                throw new AuthorizationError('Unauthorized request.');
            }

            $validationKey = $request->getQueryParams()['vkey'];

            foreach ($this->apiKeys as $apiKey) {
                if (hash_equals(hash('sha3-256', $apiKey . $validationKey), $crSignature)) {
                    $requestAuthorized = true;

                    break;
                }
            }
        } else {
            $requestBody = $request->getBody()->getContents();

            $request->getBody()->rewind();

            foreach ($this->apiKeys as $apiKey) {
                if (hash_equals(hash('sha3-256', $apiKey . $requestBody), $crSignature)) {
                    $requestAuthorized = true;

                    break;
                }
            }
        }

        if (!$requestAuthorized) {
            throw new AccessDenied('Access not allowed. Failed comparison of CR-Signature and shop hash.');
        }
    }

    protected function getPreparedResponse(ResponseInterface $response, ?array $responseData = null): ResponseInterface
    {
        $pluginHeader = "$this->platformName $this->platformVersion, Comfino $this->pluginVersion";

        if ($responseData !== null) {
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Comfino-Plugin', $pluginHeader)
                ->withBody($this->streamFactory->createStream($this->serializer->serialize($responseData)));
        }

        return $response->withHeader('Comfino-Plugin', $pluginHeader);
    }
}
