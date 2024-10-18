<?php

namespace Comfino\Common\Frontend;

use ComfinoExternal\Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Api\Client;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Paywall\PaywallViewTypeEnum;
use Comfino\Shop\Order\CartInterface;

final class PaywallRenderer extends FrontendRenderer
{
    private const PAYWALL_FRAGMENTS = ['template', 'style', 'script'];

    public function __construct(
        Client $client,
        TaggableCacheItemPoolInterface $cache,
        private readonly RendererStrategyInterface $rendererStrategy,
        ?string $cacheInvalidateUrl = null,
        ?string $configurationUrl = null
    ) {
        parent::__construct($client, $cache, $cacheInvalidateUrl, $configurationUrl);
    }

    public function renderPaywall(LoanQueryCriteria $queryCriteria): string
    {
        try {
            $fragments = $this->getFrontendFragments(self::PAYWALL_FRAGMENTS);
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }

        try {
            $paywallResponse = $this->client->getPaywall($queryCriteria, new PaywallViewTypeEnum(PaywallViewTypeEnum::PAYWALL_VIEW_LIST));
            $paywallProductsList = $paywallResponse->paywallPage;
            $fragmentsCacheMTime = [];

            if ($paywallResponse->hasHeader('Cache-MTime') && ($fragmentsCacheMTime = json_decode($paywallResponse->getHeader('Cache-MTime'), true)) === null) {
                $fragmentsCacheMTime = [];
            }

            if (count($fragmentsCacheMTime) > 0) {
                $fragmentsCacheKeysToDelete = [];

                foreach ($fragments as $fragmentName => $fragmentContents) {
                    $matches = [];
                    $regExpPattern = '';

                    switch ($fragmentName) {
                        case 'template':
                            $regExpPattern = '/<!--\[rendered:(\d+)\]-->/';
                            break;

                        case 'style':
                        case 'script':
                            $regExpPattern = '/\/\*\[cached:(\d+)\]\*\//';
                            break;
                    }

                    if ($regExpPattern !== '' && preg_match($regExpPattern, $fragmentContents, $matches)) {
                        $storedCacheMTime = (int) $matches[1];

                        if (isset($fragmentsCacheMTime[$fragmentName]) && $storedCacheMTime < $fragmentsCacheMTime[$fragmentName]) {
                            // Stored contents timestamp are less than received from Cache-MTime header - add this item to the list of keys to delete from cache.
                            $fragmentsCacheKeysToDelete[] = $fragmentName;
                        }
                    }
                }

                if (count($fragmentsCacheKeysToDelete) > 0) {
                    // Delete specified cache items to reload actual versions of resources.
                    $this->deleteFragmentsCacheEntries($fragmentsCacheKeysToDelete, $this->client->getApiLanguage());
                    // Reload deleted items from API.
                    $fragments = array_merge($fragments, $this->getFrontendFragments($fragmentsCacheKeysToDelete));
                }
            }

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

    public function getPaywallItemDetails(int $loanAmount, LoanTypeEnum $loanType, CartInterface $cart): PaywallItemDetails
    {
        try {
            $response = $this->client->getPaywallItemDetails($loanAmount, $loanType, $cart);

            return new PaywallItemDetails($response->productDetails, $response->listItemData);
        } catch (\Throwable $e) {
            return new PaywallItemDetails($this->rendererStrategy->renderErrorTemplate($e), '');
        }
    }
}
