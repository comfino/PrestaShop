<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

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
