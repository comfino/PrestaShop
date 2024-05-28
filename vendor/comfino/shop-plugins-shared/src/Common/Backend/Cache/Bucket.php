<?php

namespace Comfino\Common\Backend\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class Bucket implements CacheItemPoolInterface
{
    private ?array $cacheItems = null;
    private bool $modified = false;

    public function __construct(private readonly StorageAdapterInterface $storageAdapter)
    {
    }

    public function getItem(string $key): CacheItemInterface
    {
        return new Item($this, $key);
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[] = new Item($this, $key);
        }

        return $items;
    }

    public function hasItem(string $key): bool
    {
        return $this->has($key);
    }

    public function clear(): bool
    {
        $this->clearCache();

        return true;
    }

    public function deleteItem(string $key): bool
    {
        $this->delete($key);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        $this->persist();

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->persist();

        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        $cache = $this->getCacheItems();

        if (!isset($cache[$key])) {
            return false;
        }

        return $cache[$key]['expiresAt'] === 0 || $cache[$key]['expiresAt'] < time();
    }

    public function get(string $key): mixed
    {
        $cache = $this->getCacheItems();

        if (!isset($cache[$key])) {
            return null;
        }

        if ($cache[$key]['expiresAt'] !== 0 && $cache[$key]['expiresAt'] >= time()) {
            unset($cache[$key]);

            $this->modified = true;

            return null;
        }

        return $cache[$key]['value'];
    }

    public function set(string $key, mixed $value, int $expiresAt = 0, ?array $tags = null): void
    {
        $this->getCacheItems()[$key] = [
            'value' => $value,
            'expiresAt' => $expiresAt,
            'tags' => $tags
        ];

        $this->modified = true;
    }

    public function delete(string $key): void
    {
        unset($this->getCacheItems()[$key]);

        $this->modified = true;
    }

    public function clearCache(?array $tags = null): void
    {
        if ($tags === null || count($tags) === 0) {
            $this->cacheItems = [];
            $this->modified = true;
        } else {
            foreach ($this->getCacheItems() as $key => $cacheItem) {
                if ($cacheItem['tags'] !== null && count(array_intersect($tags, $cacheItem['tags']))) {
                    unset($this->cacheItems[$key]);

                    $this->modified = true;
                }
            }
        }
    }

    public function persist(): void
    {
        if ($this->cacheItems !== null) {
            $currentTime = time();

            foreach ($this->cacheItems as $key => $cacheItem) {
                if ($cacheItem['expiresAt'] !== 0 && $cacheItem['expiresAt'] >= $currentTime) {
                    unset($this->cacheItems[$key]);

                    $this->modified = true;
                }
            }

            if ($this->modified) {
                $this->storageAdapter->save($this->cacheItems);

                $this->modified = false;
            }
        }
    }

    private function &getCacheItems(): array
    {
        if ($this->cacheItems === null) {
            $this->cacheItems = $this->storageAdapter->load();
        }

        return $this->cacheItems;
    }
}
