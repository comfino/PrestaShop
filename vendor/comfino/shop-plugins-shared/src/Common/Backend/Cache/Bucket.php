<?php

namespace Comfino\Common\Backend\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class Bucket implements CacheItemPoolInterface
{
    /**
     * @readonly
     * @var \Comfino\Common\Backend\Cache\StorageAdapterInterface
     */
    private $storageAdapter;
    /**
     * @var mixed[]|null
     */
    private $cacheItems;
    /**
     * @var bool
     */
    private $modified = false;

    public function __construct(StorageAdapterInterface $storageAdapter)
    {
        $this->storageAdapter = $storageAdapter;
    }

    /**
     * @param string $key
     */
    public function getItem($key): CacheItemInterface
    {
        return new Item($this, $key);
    }

    /**
     * @param mixed[] $keys
     */
    public function getItems($keys = []): iterable
    {
        $items = [];

        foreach ($keys as $key) {
            $items[] = new Item($this, $key);
        }

        return $items;
    }

    /**
     * @param string $key
     */
    public function hasItem($key): bool
    {
        return $this->has($key);
    }

    public function clear(): bool
    {
        $this->clearCache();

        return true;
    }

    /**
     * @param string $key
     */
    public function deleteItem($key): bool
    {
        $this->delete($key);

        return true;
    }

    /**
     * @param mixed[] $keys
     */
    public function deleteItems($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * @param \Psr\Cache\CacheItemInterface $item
     */
    public function save($item): bool
    {
        $this->persist();

        return true;
    }

    /**
     * @param \Psr\Cache\CacheItemInterface $item
     */
    public function saveDeferred($item): bool
    {
        $this->persist();

        return true;
    }

    public function commit(): bool
    {
        return true;
    }

    /**
     * @param string $key
     */
    public function has($key): bool
    {
        $cache = $this->getCacheItems();

        if (!isset($cache[$key])) {
            return false;
        }

        return $cache[$key]['expiresAt'] === 0 || $cache[$key]['expiresAt'] < time();
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key)
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

    /**
     * @param string $key
     * @param mixed $value
     * @param int $expiresAt
     * @param mixed[]|null $tags
     */
    public function set($key, $value, $expiresAt = 0, $tags = null): void
    {
        $this->getCacheItems()[$key] = [
            'value' => $value,
            'expiresAt' => $expiresAt,
            'tags' => $tags
        ];

        $this->modified = true;
    }

    /**
     * @param string $key
     */
    public function delete($key): void
    {
        unset($this->getCacheItems()[$key]);

        $this->modified = true;
    }

    /**
     * @param mixed[]|null $tags
     */
    public function clearCache($tags = null): void
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
