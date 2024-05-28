<?php

namespace Comfino\Common\Backend\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;

class Item implements CacheItemInterface
{
    private int $expiresAt = 0;

    public function __construct(private readonly Bucket $bucket, private readonly string $key)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
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

    public function set(mixed $value): static
    {
        $this->bucket->set($this->key, $value, $this->expiresAt);

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        if ($expiration === null) {
            $this->expiresAt = 0;
        } else {
            $this->expiresAt = $expiration->getTimestamp();
        }

        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
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
