<?php

namespace Comfino\Common\Backend\RestEndpoint;

use ComfinoExternal\Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Common\Backend\Cache\ItemTypeEnum;
use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use ComfinoExternal\Psr\Cache\InvalidArgumentException;
use ComfinoExternal\Psr\Http\Message\ServerRequestInterface;

class CacheInvalidate extends RestEndpoint
{
    public function __construct(
        string $name,
        string $endpointUrl,
        private readonly TaggableCacheItemPoolInterface $cache
    ) {
        parent::__construct($name, $endpointUrl);

        $this->methods = ['POST', 'PUT', 'PATCH'];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function processRequest(ServerRequestInterface $serverRequest, ?string $endpointName = null): ?array
    {
        if (!$this->endpointPathMatch($serverRequest, $endpointName)) {
            throw new InvalidEndpoint('Endpoint path does not match request path.');
        }

        if (!is_array($requestPayload = $this->getParsedRequestBody($serverRequest))) {
            throw new InvalidRequest('Invalid request payload.');
        }

        $this->cache->invalidateTags(array_intersect($requestPayload, ItemTypeEnum::values()));

        return null;
    }
}
