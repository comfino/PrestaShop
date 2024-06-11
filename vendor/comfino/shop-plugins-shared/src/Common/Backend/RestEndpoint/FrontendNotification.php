<?php

namespace Comfino\Common\Backend\RestEndpoint;

use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use Comfino\Common\Frontend\PaywallRenderer;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

class FrontendNotification extends RestEndpoint
{
    /**
     * @readonly
     * @var \Comfino\Common\Frontend\PaywallRenderer
     */
    private $paywallRenderer;
    public function __construct(
        string $name,
        string $endpointUrl,
        PaywallRenderer $paywallRenderer
    ) {
        $this->paywallRenderer = $paywallRenderer;
        parent::__construct($name, $endpointUrl);

        $this->methods = ['POST', 'PUT', 'PATCH'];
    }

    /**
     * @throws InvalidArgumentException
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

        $this->paywallRenderer->savePaywallFragments($requestPayload);

        return null;
    }
}
