<?php

namespace Comfino\Common\Frontend;

final readonly class IframeRenderer
{
    public function __construct(private string $platformName, private string $platformVersion)
    {
    }

    public function renderPaywallIframe(string $iframeUrl): string
    {
        return sprintf(
            '<iframe id="comfino-paywall-container" src="%s" referrerpolicy="strict-origin" loading="lazy" class="comfino-paywall" scrolling="no" onload="ComfinoPaywallFrontend.onload(this, \'%s\', \'%s\')"></iframe>',
            $iframeUrl,
            $this->platformName,
            $this->platformVersion
        );
    }

    public function renderWidgetIframe(): string
    {
        return '';
    }
}
