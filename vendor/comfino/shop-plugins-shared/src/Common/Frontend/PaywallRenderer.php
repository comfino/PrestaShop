<?php

namespace Comfino\Common\Frontend;

use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Api\Client;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Paywall\PaywallViewTypeEnum;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;

final class PaywallRenderer
{
    /**
     * @readonly
     * @var \Comfino\Api\Client
     */
    private $client;
    /**
     * @readonly
     * @var \Cache\TagInterop\TaggableCacheItemPoolInterface
     */
    private $cache;
    /**
     * @readonly
     * @var \Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface
     */
    private $rendererStrategy;
    /**
     * @readonly
     * @var string|null
     */
    private $cacheInvalidateUrl;
    /**
     * @readonly
     * @var string|null
     */
    private $configurationUrl;
    private const PAYWALL_GUI_FRAGMENTS = ['template', 'style', 'script', 'frontend_style', 'frontend_script'];

    public function __construct(Client $client, TaggableCacheItemPoolInterface $cache, RendererStrategyInterface $rendererStrategy, ?string $cacheInvalidateUrl = null, ?string $configurationUrl = null)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->rendererStrategy = $rendererStrategy;
        $this->cacheInvalidateUrl = $cacheInvalidateUrl;
        $this->configurationUrl = $configurationUrl;
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
            } catch (InvalidArgumentException $exception) {
            }
        }

        if (count($fragments) < count(self::PAYWALL_GUI_FRAGMENTS)) {
            try {
                $fragments = $this->fetchPaywallFragments();

                $this->savePaywallFragments($fragments, $language);
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
                    ['{PAYWALL_STYLE}', '{LOAN_AMOUNT}', '{PAYWALL_PRODUCTS_LIST}', '{PAYWALL_SCRIPT}'],
                    [$fragments['style'], $queryCriteria->loanAmount, $paywallProductsList, $fragments['script']],
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
        return $this->client->getPaywallFragments($this->cacheInvalidateUrl, $this->configurationUrl)->paywallFragments;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function savePaywallFragments(array $fragments, ?string $language = null): void
    {
        foreach ($fragments as $fragmentName => $fragmentContents) {
            if (!in_array($fragmentName, self::PAYWALL_GUI_FRAGMENTS, true)) {
                continue;
            }

            if ($language !== null && !is_array($fragmentContents)) {
                $this->cache->saveDeferred(
                    $this->cache->getItem($this->getItemKey($fragmentName, $language))
                        ->set($fragmentContents)
                        ->setTags(["paywall_$fragmentName"])
                );
            } elseif (is_array($fragmentContents)) {
                foreach ($fragmentContents as $fragmentLanguage => $fragmentLanguageContents) {
                    $this->cache->saveDeferred(
                        $this->cache->getItem($this->getItemKey($fragmentName, $fragmentLanguage))
                            ->set($fragmentLanguageContents)
                            ->setTags(["paywall_$fragmentName"])
                    );
                }
            } else {
                $this->cache->saveDeferred(
                    $this->cache->getItem($this->getItemKey($fragmentName, ''))
                        ->set($fragmentContents)
                        ->setTags(["paywall_$fragmentName"])
                );
            }
        }

        $this->cache->commit();
    }

    private function getItemKey(string $fragmentName, string $language): string
    {
        return "comfino_paywall:$fragmentName" . ($fragmentName === 'template' ? ":$language" : '');
    }
}
