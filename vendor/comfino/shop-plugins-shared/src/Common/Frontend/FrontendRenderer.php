<?php

namespace Comfino\Common\Frontend;

use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Api\Client;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;

abstract class FrontendRenderer
{
    /**
     * @readonly
     * @var \Comfino\Api\Client
     */
    protected $client;
    /**
     * @readonly
     * @var \Cache\TagInterop\TaggableCacheItemPoolInterface
     */
    protected $cache;
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

    public function __construct(Client $client, TaggableCacheItemPoolInterface $cache, ?string $cacheInvalidateUrl = null, ?string $configurationUrl = null)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->cacheInvalidateUrl = $cacheInvalidateUrl;
        $this->configurationUrl = $configurationUrl;
    }

    /**
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     * @param mixed[] $fragmentsToGet
     */
    protected function getFrontendFragments($fragmentsToGet): array
    {
        $language = $this->client->getApiLanguage();
        $fragments = [];

        foreach ($fragmentsToGet as $fragmentName) {
            try {
                $itemKey = $this->getItemKey($fragmentName, $language);

                if ($this->cache->getItem($itemKey)->isHit()) {
                    $fragments[$fragmentName] = $this->cache->getItem($itemKey)->get();
                }
            } catch (InvalidArgumentException $exception) {
            }
        }

        if (count($fragments) < count($fragmentsToGet)) {
            $this->savePaywallFragments(
                $fragments = $this->client->getPaywallFragments($this->cacheInvalidateUrl, $this->configurationUrl)->paywallFragments,
                $language
            );
        }

        return $fragments;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function savePaywallFragments(array $fragments, ?string $language = null): void
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

    /**
     * @param string $fragmentName
     * @param string $language
     */
    protected function getItemKey($fragmentName, $language): string
    {
        return "comfino_paywall:$fragmentName" . ($fragmentName === 'template' ? ":$language" : '');
    }
}
