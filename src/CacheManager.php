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

namespace Comfino;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use Psr\Cache\InvalidArgumentException;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CacheManager
{
    /** @var string */
    private static $cache_root_path;
    /** @var FilesystemCachePool|ArrayCachePool */
    private static $cache;

    public static function init(\PaymentModule $module): void
    {
        self::$cache_root_path = _PS_MODULE_DIR_ . $module->name . '/var';
    }

    public static function get(string $key, $default = null)
    {
        try {
            return self::getCachePool()->get($key, $default);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
        }

        return $default;
    }

    public static function set(string $key, $value, int $ttl = 0, ?array $tags = null): void
    {
        try {
            $item = self::getCachePool()->getItem($key)->set($value);

            if ($ttl > 0) {
                $item->expiresAfter($ttl);
            }

            if (!empty($tags)) {
                $item->setTags($tags);
            }

            self::getCachePool()->save($item);
        } catch (InvalidArgumentException $e) {
        }
    }

    public static function getCachePool(): AbstractCachePool
    {
        if (self::$cache === null) {
            try {
                self::$cache = new FilesystemCachePool(new Filesystem(new Local(self::$cache_root_path)));
            } catch (\Throwable $e) {
                self::$cache = new ArrayCachePool();
            }
        }

        return self::$cache;
    }
}
