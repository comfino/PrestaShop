<?php

namespace Comfino\Common\Backend\Cache;

interface StorageAdapterInterface
{
    public function load(): array;
    /**
     * @param mixed[] $cacheData
     */
    public function save($cacheData): void;
}
