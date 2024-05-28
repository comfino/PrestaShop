<?php

namespace Comfino\Common\Backend\RestEndpoint;

use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use Comfino\Common\Shop\Order\StatusManager;
use Psr\Http\Message\ServerRequestInterface;

final class StatusNotification extends RestEndpoint
{
    public function __construct(
        string $name,
        string $endpointUrl,
        private readonly StatusManager $statusManager,
        private readonly array $forbiddenStatuses,
        private readonly array $ignoredStatuses
    ) {
        parent::__construct($name, $endpointUrl);

        $this->methods = ['POST', 'PUT', 'PATCH'];
    }

    /**
     * @inheritDoc
     */
    public function processRequest(ServerRequestInterface $serverRequest): ?array
    {
        if (!$this->endpointPathMatch($serverRequest)) {
            throw new InvalidEndpoint('Endpoint path does not match request path.');
        }

        if (!is_array($requestPayload = $serverRequest->getParsedBody())) {
            throw new InvalidRequest('Invalid request payload.');
        }

        if (!isset($requestPayload['status'])) {
            throw new InvalidRequest('Status must be set.');
        }

        if (in_array($requestPayload['status'], $this->ignoredStatuses, true)) {
            return null;
        }

        if (!isset($requestPayload['externalId'])) {
            throw new InvalidRequest('External ID must be set.');
        }

        if (in_array($requestPayload['status'], $this->forbiddenStatuses, true)) {
            throw new InvalidRequest('Invalid status "' . $requestPayload['status'] . '".');
        }

        try {
            $this->statusManager->setOrderStatus($requestPayload['externalId'], $requestPayload['status']);
        } catch (\Exception $e) {
            throw new InvalidRequest($e->getMessage());
        }

        return null;
    }
}
