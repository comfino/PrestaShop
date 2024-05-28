<?php

namespace Comfino\Common\Backend\Cache;

interface StorageAdapterInterface
{
    public function load(): array;
    public function save(array $cacheData): void;
}
