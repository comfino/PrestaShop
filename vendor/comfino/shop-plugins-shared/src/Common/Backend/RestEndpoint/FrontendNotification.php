<?php

namespace Comfino\Common\Backend\RestEndpoint;

use Comfino\Common\Backend\Cache\StorageAdapterInterface;
use Comfino\Common\Backend\CacheManager;
use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use Comfino\Common\Frontend\FrontendManager;
use Psr\Http\Message\ServerRequestInterface;

class FrontendNotification extends RestEndpoint
{
    /**
     * @readonly
     * @var \Comfino\Common\Backend\CacheManager
     */
    private $cacheManager;
    /**
     * @readonly
     * @var \Comfino\Common\Backend\Cache\StorageAdapterInterface
     */
    private $storageAdapter;
    public function __construct(
        string $name,
        string $endpointUrl,
        CacheManager $cacheManager,
        StorageAdapterInterface $storageAdapter
    ) {
        $this->cacheManager = $cacheManager;
        $this->storageAdapter = $storageAdapter;
        parent::__construct($name, $endpointUrl);

        $this->methods = ['POST', 'PUT', 'PATCH'];
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $serverRequest
     * @param string|null $endpointName
     */
    public function processRequest($serverRequest, $endpointName = null): ?array
    {
        if (!$this->endpointPathMatch($serverRequest, $endpointName)) {
            throw new InvalidEndpoint('Endpoint path does not match request path.');
        }

        if (!is_array($requestPayload = $serverRequest->getParsedBody())) {
            throw new InvalidRequest('Invalid request payload.');
        }

        foreach ($requestPayload as $key => $value) {
            if (in_array($key, FrontendManager::FRONTEND_FRAGMENTS, true)) {
                $this->cacheManager->getCacheBucket(FrontendManager::CACHE_BUCKET_NAME, $this->storageAdapter)->set($key, $value);
            }
        }

        return null;
    }
}
