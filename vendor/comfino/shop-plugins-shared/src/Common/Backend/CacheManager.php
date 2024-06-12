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

    /**
     * @param StorageAdapterInterface[]|null $cacheBuckets ['cacheBucketName' => StorageAdapterInterface]
     */
    public static function getInstance(?array $cacheBuckets = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($cacheBuckets);
        } elseif ($cacheBuckets !== null) {
            self::$instance->initCacheBuckets($cacheBuckets);
        }

        return self::$instance;
    }

    /**
     * @param StorageAdapterInterface[]|null $cacheBuckets ['cacheBucketName' => StorageAdapterInterface]
     */
    private function __construct(?array $cacheBuckets)
    {
        if ($cacheBuckets !== null) {
            $this->initCacheBuckets($cacheBuckets);
        }
    }

    public function __destruct()
    {
        foreach ($this->cacheBuckets as $cacheBucket) {
            $cacheBucket->persist();
        }
    }

    /**
     * @param StorageAdapterInterface[] $cacheBuckets ['cacheBucketName' => StorageAdapterInterface]
     */
    public function initCacheBuckets(array $cacheBuckets): void
    {
        foreach ($cacheBuckets as $cacheBucketName => $storageAdapter) {
            if ($storageAdapter instanceof StorageAdapterInterface) {
                $this->cacheBuckets[$cacheBucketName] = new Bucket($storageAdapter);
            }
        }
    }

    public function getCacheBucket(string $cacheBucketName): Bucket
    {
        if (!isset($this->cacheBuckets[$cacheBucketName])) {
            throw new \RuntimeException(sprintf('Unknown cache bucket %s.', $cacheBucketName));
        }

        return $this->cacheBuckets[$cacheBucketName];
    }

    /**
     * @param string[]|null $tags
     */
    public function clear(?array $tags = null): void
    {
        foreach ($this->cacheBuckets as $cacheBucket) {
            $cacheBucket->clearCache($tags);
        }
    }
}
