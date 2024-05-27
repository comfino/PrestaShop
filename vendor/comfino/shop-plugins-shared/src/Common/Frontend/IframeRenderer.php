<?php

namespace Comfino\Common\Frontend;

final class IframeRenderer
{
    /**
     * @readonly
     * @var string
     */
    private $platformName;
    /**
     * @readonly
     * @var string
     */
    private $platformVersion;
    public function __construct(string $platformName, string $platformVersion)
    {
        $this->platformName = $platformName;
        $this->platformVersion = $platformVersion;
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
