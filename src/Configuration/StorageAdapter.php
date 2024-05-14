<?php

namespace Comfino\Configuration;

use Comfino\Common\Backend\Configuration\StorageAdapterInterface;
use Comfino\ConfigManager;

class StorageAdapter implements StorageAdapterInterface
{
    public function load(): array
    {
        return ConfigManager::load();
    }

    public function save($configurationOptions): void
    {
        ConfigManager::save($configurationOptions);
    }
}
