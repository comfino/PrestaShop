<?php

namespace Comfino\Api\Request;

use Comfino\Api\Request;

class GetPaywallFragments extends Request
{
    public function __construct(?string $notificationUrl, ?string $configurationUrl)
    {
        $this->setRequestMethod('GET');
        $this->setApiEndpointPath('shop-plugin-paywall-fragments');

        if (!empty($notificationUrl) || !empty($configurationUrl)) {
            $requestHeaders = [];

            if (!empty($notificationUrl)) {
                $requestHeaders['Comfino-Paywall-Notification-Url'] = $notificationUrl;
            }
            if (!empty($notificationUrl)) {
                $requestHeaders['Comfino-Configuration-Url'] = $configurationUrl;
            }

            $this->setRequestHeaders($requestHeaders);
        }
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
