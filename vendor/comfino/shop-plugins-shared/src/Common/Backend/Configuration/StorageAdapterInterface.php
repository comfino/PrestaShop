<?php

namespace Comfino\Common\Backend\Configuration;

interface StorageAdapterInterface
{
    public function load(): array;
    public function save(array $configurationOptions): void;
}
