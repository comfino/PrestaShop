<?php

namespace Comfino\Common\Frontend;

use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Api\Client;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Paywall\PaywallViewTypeEnum;

final class PaywallRenderer extends FrontendRenderer
{
    /**
     * @readonly
     * @var \Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface
     */
    private $rendererStrategy;
    private const PAYWALL_FRAGMENTS = ['template', 'style', 'script'];

    public function __construct(
        Client $client,
        TaggableCacheItemPoolInterface $cache,
        RendererStrategyInterface $rendererStrategy,
        ?string $cacheInvalidateUrl = null,
        ?string $configurationUrl = null
    ) {
        $this->rendererStrategy = $rendererStrategy;
        parent::__construct($client, $cache, $cacheInvalidateUrl, $configurationUrl);
    }

    /**
     * @param \Comfino\Api\Dto\Payment\LoanQueryCriteria $queryCriteria
     */
    public function renderPaywall($queryCriteria): string
    {
        try {
            $fragments = $this->getFrontendFragments(self::PAYWALL_FRAGMENTS);
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }

        try {
            $paywallProductsList = $this->client->getPaywall(
                $queryCriteria,
                new PaywallViewTypeEnum(PaywallViewTypeEnum::PAYWALL_VIEW_LIST)
            )->paywallPage;

            return $this->rendererStrategy->renderPaywallTemplate(
                str_replace(
                    ['{PAYWALL_STYLE}', '{LOAN_AMOUNT}', '{PAYWALL_PRODUCTS_LIST}', '{PAYWALL_SCRIPT}'],
                    [$fragments['style'], $queryCriteria->loanAmount, $paywallProductsList, $fragments['script']],
                    $fragments['template']
                )
            );
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }
    }
}
