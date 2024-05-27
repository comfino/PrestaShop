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

namespace Comfino\Cache;

use Comfino\Common\Backend\Cache\StorageAdapterInterface;
use Comfino\Extended\Api\Serializer\Json;

class StorageAdapter implements StorageAdapterInterface
{
    /** @var string */
    private $cache_id;

    public function __construct(string $cache_id)
    {
        $this->cache_id = $cache_id;
    }

    public function load(): array
    {
        if (($cache_storage = @file_get_contents(_PS_MODULE_DIR_ . "comfino/var/cache/$this->cache_id")) === false) {
            return [];
        }

        try {
            return (new Json())->unserialize($cache_storage);
        } catch (\Exception $e) {
        }

        return [];
    }

    public function save($cacheData): void
    {
        $cache_dir = _PS_MODULE_DIR_ . 'comfino/var/cache';

        if (!mkdir($cache_dir, 0755, true) && !is_dir($cache_dir)) {
            return;
        }

        try {
            @file_put_contents("$cache_dir/$this->cache_id", (new Json())->serialize($cacheData), LOCK_EX);
        } catch (\Exception $e) {
        }
    }
}
