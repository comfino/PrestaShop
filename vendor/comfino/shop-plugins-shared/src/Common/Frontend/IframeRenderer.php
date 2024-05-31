<?php

namespace Comfino\Common\Frontend;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Psr\Http\Client\ClientExceptionInterface;

final class IframeRenderer
{
    /**
     * @readonly
     * @var \Comfino\Common\Frontend\PaywallRenderer
     */
    private $paywallRenderer;
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
    public function __construct(PaywallRenderer $paywallRenderer, string $platformName, string $platformVersion)
    {
        $this->paywallRenderer = $paywallRenderer;
        $this->platformName = $platformName;
        $this->platformVersion = $platformVersion;
    }

    /**
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function renderPaywallIframe(string $iframeUrl): string
    {
        $fragments = $this->paywallRenderer->fetchPaywallFragments();

        return sprintf(
            '<style>%s</style><iframe id="comfino-paywall-container" src="%s" referrerpolicy="strict-origin" loading="lazy" class="comfino-paywall" scrolling="no" onload="ComfinoPaywallFrontend.onload(this, \'%s\', \'%s\')"></iframe><script>%s</script>',
            $fragments['frontend_style'] ?? '',
            $iframeUrl,
            $this->platformName,
            $this->platformVersion,
            $fragments['frontend_script'] ?? ''
        );
    }

    public function renderWidgetIframe(): string
    {
        return '';
    }
}
