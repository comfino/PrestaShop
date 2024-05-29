<?php

namespace Comfino\Common\Backend;

use Comfino\Common\Backend\Cache\Bucket;
use Comfino\Common\Backend\Cache\StorageAdapterInterface;

final class CacheManager
{
    /**
     * @var $this|null
     */
    private static $instance;

    /** @var Bucket[] */
    private $cacheBuckets = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function __destruct()
    {
        foreach ($this->cacheBuckets as $cacheBucket) {
            $cacheBucket->persist();
        }
    }

    public function getCacheBucket(string $cacheBucketName, StorageAdapterInterface $storageAdapter): Bucket
    {
        if (!isset($this->cacheBuckets[$cacheBucketName])) {
            $this->cacheBuckets[$cacheBucketName] = new Bucket($storageAdapter);
        }

        return $this->cacheBuckets[$cacheBucketName];
    }
}
