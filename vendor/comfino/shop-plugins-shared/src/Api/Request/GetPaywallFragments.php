<?php

namespace Comfino\Api\Request;

use Comfino\Api\Request;

class GetPaywallFragments extends Request
{
    public function __construct()
    {
        $this->setRequestMethod('GET');
        $this->setApiEndpointPath('shop-plugin-paywall-fragments');
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
