<?php

namespace Comfino\Common\Backend\Logger;

interface StorageAdapterInterface
{
    public function save(string $errorPrefix, string $errorMessage): void;
}
