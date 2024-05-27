<?php

namespace Comfino\Common\Backend\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;

class Item implements CacheItemInterface
{
    /**
     * @readonly
     * @var \Comfino\Common\Backend\Cache\Bucket
     */
    private $bucket;
    /**
     * @readonly
     * @var string
     */
    private $key;
    /**
     * @var int
     */
    private $expiresAt = 0;

    public function __construct(Bucket $bucket, string $key)
    {
        $this->bucket = $bucket;
        $this->key = $key;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->bucket->get($this->key);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isHit(): bool
    {
        return $this->bucket->hasItem($this->key);
    }

    /**
     * @param mixed $value
     * @return static
     */
    public function set($value)
    {
        $this->bucket->set($this->key, $value, $this->expiresAt);

        return $this;
    }

    /**
     * @param \DateTimeInterface|null $expiration
     * @return static
     */
    public function expiresAt($expiration)
    {
        if ($expiration === null) {
            $this->expiresAt = 0;
        } else {
            $this->expiresAt = $expiration->getTimestamp();
        }

        return $this;
    }

    /**
     * @param \DateInterval|int|null $time
     * @return static
     */
    public function expiresAfter($time)
    {
        if ($this === null) {
            $this->expiresAt = 0;
        } elseif ($time instanceof \DateInterval) {
            $this->expiresAt = (new \DateTime())->add($time)->getTimestamp();
        } else {
            $this->expiresAt = time() + (int) $time;
        }

        return $this;
    }
}
