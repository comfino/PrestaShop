<?php

namespace Comfino\Common\Frontend;

use Comfino\Api\Client;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Paywall\PaywallViewTypeEnum;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;

final readonly class PaywallRenderer
{
    private const PAYWALL_GUI_FRAGMENTS = ['template', 'style', 'script', 'frontend_style', 'frontend_script'];

    public function __construct(
        private Client $client,
        private CacheItemPoolInterface $cache,
        private RendererStrategyInterface $rendererStrategy
    ) {
    }

    public function renderPaywall(LoanQueryCriteria $queryCriteria): string
    {
        $language = $this->client->getApiLanguage();
        $fragments = [];

        foreach (self::PAYWALL_GUI_FRAGMENTS as $fragmentName) {
            try {
                $itemKey = $this->getItemKey($fragmentName, $language);

                if ($this->cache->getItem($itemKey)->isHit()) {
                    $fragments[$fragmentName] = $this->cache->getItem($itemKey)->get();
                }
            } catch (InvalidArgumentException) {
            }
        }

        if (count($fragments) < count(self::PAYWALL_GUI_FRAGMENTS)) {
            try {
                $fragments = $this->fetchPaywallFragments();

                foreach ($fragments as $fragmentName => $fragmentContents) {
                    $this->cache->saveDeferred(
                        $this->cache->getItem($this->getItemKey($fragmentName, $language))->set($fragmentContents)
                    );
                }

                $this->cache->commit();
            } catch (\Throwable $e) {
                return $this->rendererStrategy->renderErrorTemplate($e);
            }
        }

        try {
            $paywallProductsList = $this->client->getPaywall(
                $queryCriteria,
                new PaywallViewTypeEnum(PaywallViewTypeEnum::PAYWALL_VIEW_LIST)
            )->paywallPage;

            return $this->rendererStrategy->renderPaywallTemplate(
                str_replace(
                    ['{PAYWALL_STYLE}', '{PAYWALL_PRODUCTS_LIST}', '{PAYWALL_SCRIPT}'],
                    [$fragments['style'], $paywallProductsList, $fragments['script']],
                    $fragments['template']
                )
            );
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }
    }

    /**
     * @return string[]
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function fetchPaywallFragments(): array
    {
        return $this->client->getPaywallFragments()->paywallFragments;
    }

    private function getItemKey(string $fragmentName, string $language): string
    {
        return "comfino_paywall:$fragmentName" . ($fragmentName === 'template' ? ":$language" : '');
    }
}
