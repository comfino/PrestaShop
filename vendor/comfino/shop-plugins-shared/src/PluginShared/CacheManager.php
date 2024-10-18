<?php

namespace Comfino\PluginShared;

use ComfinoExternal\Cache\Adapter\Common\AbstractCachePool;
use ComfinoExternal\Cache\Adapter\Filesystem\FilesystemCachePool;
use ComfinoExternal\Cache\Adapter\PHPArray\ArrayCachePool;
use ComfinoExternal\League\Flysystem\Adapter\Local;
use ComfinoExternal\League\Flysystem\Filesystem;
use ComfinoExternal\Psr\Cache\InvalidArgumentException;

final class CacheManager
{
    private static string $cacheRootPath;
    private static FilesystemCachePool|ArrayCachePool|null $cache;

    public static function init(string $cacheRootPath): void
    {
        self::$cacheRootPath = $cacheRootPath;
        self::$cache = null;
    }

    public static function get(string $key, $default = null)
    {
        try {
            return self::getCachePool()->get($key, $default);
        } catch (\ComfinoExternal\Psr\SimpleCache\InvalidArgumentException) {
        }

        return $default;
    }

    public static function set(string $key, $value, int $ttl = 0, ?array $tags = null): void
    {
        try {
            $item = self::getCachePool()->getItem($key)->set($value);

            if ($ttl > 0) {
                $item->expiresAfter($ttl);
            }

            if (!empty($tags)) {
                $item->setTags($tags);
            }

            self::getCachePool()->save($item);
        } catch (InvalidArgumentException) {
        }
    }

    public static function getCachePool(): AbstractCachePool
    {
        if (self::$cache === null) {
            if (empty(self::$cacheRootPath)) {
                return self::$cache = new ArrayCachePool();
            }

            try {
                self::$cache = new FilesystemCachePool(new Filesystem(new Local(self::$cacheRootPath)));
            } catch (\Throwable) {
                self::$cache = new ArrayCachePool();
            }
        }

        return self::$cache;
    }
}
