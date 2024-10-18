<?php

namespace Comfino\Extended\Api\Request;

use Comfino\Api\Request;

/**
 * Cart abandonment notifying request.
 */
class NotifyAbandonedCart extends Request
{
    public function __construct(private readonly string $type)
    {
        $this->setRequestMethod('POST');
        $this->setApiEndpointPath('abandoned_cart');
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return ['type' => $this->type];
    }
}
