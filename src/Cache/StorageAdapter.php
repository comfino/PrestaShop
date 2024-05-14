<?php

namespace Comfino\Cache;

use Comfino\Common\Backend\Cache\StorageAdapterInterface;
use Comfino\Extended\Api\Serializer\Json;

class StorageAdapter implements StorageAdapterInterface
{
    /** @var string */
    private $cache_id;

    public function __construct(string $cache_id)
    {
        $this->cache_id = $cache_id;
    }

    public function load(): array
    {
        if (($cache_storage = @file_get_contents(_PS_MODULE_DIR_ . "comfino/var/cache/$this->cache_id")) === false) {
            return [];
        }

        try {
            return (new Json())->unserialize($cache_storage);
        } catch (\Exception $e) {
        }

        return [];
    }

    public function save($cacheData): void
    {
        $cache_dir = _PS_MODULE_DIR_ . 'comfino/var/cache';

        if (!mkdir($cache_dir, 0755, true) && !is_dir($cache_dir)) {
            return;
        }

        try {
            @file_put_contents("$cache_dir/$this->cache_id", (new Json())->serialize($cacheData), LOCK_EX);
        } catch (\Exception $e) {
        }
    }
}
