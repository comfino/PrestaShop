<?php

namespace Comfino\Common\Frontend;

use ComfinoExternal\Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Api\Client;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use ComfinoExternal\Psr\Cache\InvalidArgumentException;
use ComfinoExternal\Psr\Http\Client\ClientExceptionInterface;

abstract class FrontendRenderer
{
    private const PAYWALL_GUI_FRAGMENTS = ['template', 'style', 'script', 'frontend_style', 'frontend_script'];

    /** @var string[] */
    private static array $fragments = [];

    public function __construct(
        protected readonly Client $client,
        protected readonly TaggableCacheItemPoolInterface $cache,
        private readonly ?string $cacheInvalidateUrl = null,
        private readonly ?string $configurationUrl = null
    ) {
    }

    /**
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     * @throws InvalidArgumentException
     */
    protected function getFrontendFragments(array $fragmentsToGet): array
    {
        $language = $this->client->getApiLanguage();
        $fragments = [];

        foreach ($fragmentsToGet as $fragmentName) {
            if (isset(self::$fragments[$fragmentName])) {
                $fragments[$fragmentName] = self::$fragments[$fragmentName];

                continue;
            }

            try {
                $itemKey = $this->getItemKey($fragmentName, $language);

                if ($this->cache->getItem($itemKey)->isHit()) {
                    $fragments[$fragmentName] = $this->cache->getItem($itemKey)->get();
                }
            } catch (InvalidArgumentException) {
            }
        }

        if (count($fragments) < count($fragmentsToGet)) {
            $paywallFragments = $this->client->getPaywallFragments($this->cacheInvalidateUrl, $this->configurationUrl);
            $fragments = $paywallFragments->paywallFragments;
            $fragmentsCacheTtl = [];

            if ($paywallFragments->hasHeader('Cache-TTL') && ($fragmentsCacheTtl = json_decode($paywallFragments->getHeader('Cache-TTL'), true)) === null) {
                $fragmentsCacheTtl = [];
            }

            $this->savePaywallFragments($fragments, $fragmentsCacheTtl, $language);

            self::$fragments = array_merge(self::$fragments, $fragments);
        }

        return $fragments;
    }

    /**
     * @param string[] $cacheKeysToDelete
     */
    protected function deleteFragmentsCacheEntries(array $cacheKeysToDelete, string $language): void
    {
        try {
            $this->cache->deleteItems(array_map(fn (string $fragmentName): string => $this->getItemKey($fragmentName, $language), $cacheKeysToDelete));
        } catch (InvalidArgumentException) {
        }

        foreach ($cacheKeysToDelete as $cacheKeyToDelete) {
            unset(self::$fragments[$cacheKeyToDelete]);
        }
    }

    /**
     * @param string[] $fragments
     * @param int[] $fragmentsCacheTtl
     * @throws InvalidArgumentException
     */
    private function savePaywallFragments(array $fragments, array $fragmentsCacheTtl, ?string $language = null): void
    {
        foreach ($fragments as $fragmentName => $fragmentContents) {
            if (!in_array($fragmentName, self::PAYWALL_GUI_FRAGMENTS, true)) {
                continue;
            }

            if ($language !== null && !is_array($fragmentContents)) {
                $cacheItem = $this->cache->getItem($this->getItemKey($fragmentName, $language))
                    ->set($fragmentContents)
                    ->setTags(["paywall_$fragmentName"]);

                if (isset($fragmentsCacheTtl[$fragmentName]) && $fragmentsCacheTtl[$fragmentName] > 0) {
                    $cacheItem->expiresAfter($fragmentsCacheTtl[$fragmentName]);
                }

                $this->cache->saveDeferred($cacheItem);
            } elseif (is_array($fragmentContents)) {
                foreach ($fragmentContents as $fragmentLanguage => $fragmentLanguageContents) {
                    $cacheItem = $this->cache->getItem($this->getItemKey($fragmentName, $fragmentLanguage))
                        ->set($fragmentLanguageContents)
                        ->setTags(["paywall_$fragmentName"]);

                    if (isset($fragmentsCacheTtl[$fragmentName]) && $fragmentsCacheTtl[$fragmentName] > 0) {
                        $cacheItem->expiresAfter($fragmentsCacheTtl[$fragmentName]);
                    }

                    $this->cache->saveDeferred($cacheItem);
                }
            } else {
                $cacheItem = $this->cache->getItem($this->getItemKey($fragmentName, ''))
                    ->set($fragmentContents)
                    ->setTags(["paywall_$fragmentName"]);

                if (isset($fragmentsCacheTtl[$fragmentName]) && $fragmentsCacheTtl[$fragmentName] > 0) {
                    $cacheItem->expiresAfter($fragmentsCacheTtl[$fragmentName]);
                }

                $this->cache->saveDeferred($cacheItem);
            }
        }

        $this->cache->commit();
    }

    private function getItemKey(string $fragmentName, string $language): string
    {
        return "comfino_paywall.$fragmentName" . ($fragmentName === 'template' ? ".$language" : '');
    }
}
