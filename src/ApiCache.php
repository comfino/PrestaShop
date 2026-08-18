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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Time-limited cache for the slow-changing Comfino API responses, plus a circuit breaker for the calls that
 * happen while shop pages are being rendered.
 *
 * Two problems are solved here. Without a cache every product page and every checkout page issues a live
 * request for data that changes at most a few times a year, which turns an upstream slowdown into a storefront
 * slowdown and lets an unauthenticated endpoint amplify cheap inbound requests into an upstream load. Without a
 * breaker a sustained outage means every single page render pays the full connection timeout.
 *
 * Both the cache and the breaker state live in PrestaShop's `Configuration` store, so they are shared by all
 * PHP workers and survive between requests without needing a writable cache directory.
 */
class ApiCache
{
    /** Financial product lists rarely change; an hour of staleness is not observable to a customer. */
    const PRODUCT_TYPES_TTL = 3600;

    /** The widget key is tied to the merchant account and effectively never changes. */
    const WIDGET_KEY_TTL = 86400;

    /** Consecutive failures needed to open the circuit. */
    const FAILURE_THRESHOLD = 3;

    /** How long the circuit stays open before a single request is allowed through again. */
    const OPEN_STATE_DURATION = 300;

    const CACHE_STORAGE_KEY = 'COMFINO_API_CACHE';
    const BREAKER_STORAGE_KEY = 'COMFINO_API_BREAKER';

    /** Hard cap on the serialized cache payload, so a large upstream response cannot bloat the settings row. */
    const MAX_CACHE_SIZE = 65536;

    /**
     * How long an expired entry is kept so it can still be served while the API is unreachable. Past this point
     * it is dropped: reviving month-old financial product data is worse than withdrawing the payment method.
     */
    const STALE_RETENTION = 604800;

    /**
     * Returns a cached value which has not expired yet or null.
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public static function get($key)
    {
        $entry = self::readEntry($key);

        if ($entry === null || $entry['expires_at'] <= time()) {
            return null;
        }

        return $entry['value'];
    }

    /**
     * Returns a cached value regardless of its expiry. Used when the API cannot be reached: serving a stale
     * financial product list is better than withdrawing the payment method over a transient outage.
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public static function getStale($key)
    {
        $entry = self::readEntry($key);

        if ($entry === null || ($entry['expires_at'] + self::STALE_RETENTION) <= time()) {
            return null;
        }

        return $entry['value'];
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     *
     * @return void
     */
    public static function set($key, $value, $ttl)
    {
        $cache = self::prune(self::readCache());
        $cache[$key] = ['value' => $value, 'expires_at' => time() + (int) $ttl];

        self::writeCache($cache);
    }

    /**
     * Drops entries which are past the stale retention window. Cache keys carry a credential fingerprint, so
     * without this the store would keep an entry for every API key the shop has ever used.
     *
     * @param array $cache
     *
     * @return array
     */
    private static function prune(array $cache)
    {
        $cutoff = time() - self::STALE_RETENTION;

        foreach ($cache as $key => $entry) {
            if (!is_array($entry) || !isset($entry['expires_at']) || (int) $entry['expires_at'] <= $cutoff) {
                unset($cache[$key]);
            }
        }

        return $cache;
    }

    /**
     * True while the breaker is open, meaning no request should be attempted. One request is let through once
     * the cooldown elapses; its outcome either closes the breaker or restarts the cooldown.
     *
     * @return bool
     */
    public static function isCircuitOpen()
    {
        $state = self::readBreakerState();

        if ($state['failures'] < self::FAILURE_THRESHOLD) {
            return false;
        }

        return ($state['opened_at'] + self::OPEN_STATE_DURATION) > time();
    }

    /**
     * @return void
     */
    public static function recordSuccess()
    {
        $state = self::readBreakerState();

        if ($state['failures'] === 0) {
            // Nothing to reset - avoid a settings writing on the happy path.
            return;
        }

        self::writeBreakerState(['failures' => 0, 'opened_at' => 0]);
    }

    /**
     * @return void
     */
    public static function recordFailure()
    {
        $state = self::readBreakerState();
        $failures = $state['failures'] + 1;

        self::writeBreakerState([
            'failures' => $failures,
            'opened_at' => $failures >= self::FAILURE_THRESHOLD ? time() : $state['opened_at'],
        ]);
    }

    /**
     * Drops every cached entry and closes the breaker. Called when the API key or the environment changes, so
     * data fetched with the previous credentials is never reused.
     *
     * @return void
     */
    public static function clear()
    {
        \Configuration::updateValue(self::CACHE_STORAGE_KEY, '');
        \Configuration::updateValue(self::BREAKER_STORAGE_KEY, '');
    }

    /**
     * @param string $key
     *
     * @return array|null Entry with the `value` and `expires_at` keys, or null when absent or malformed.
     */
    private static function readEntry($key)
    {
        $cache = self::readCache();

        if (!isset($cache[$key]) || !is_array($cache[$key]) || !array_key_exists('value', $cache[$key])) {
            return null;
        }

        return [
            'value' => $cache[$key]['value'],
            'expires_at' => isset($cache[$key]['expires_at']) ? (int) $cache[$key]['expires_at'] : 0,
        ];
    }

    /**
     * @return array
     */
    private static function readCache()
    {
        $raw = (string) \Configuration::get(self::CACHE_STORAGE_KEY);

        if ($raw === '') {
            return [];
        }

        $cache = json_decode($raw, true);

        return is_array($cache) ? $cache : [];
    }

    /**
     * @param array $cache
     *
     * @return void
     */
    private static function writeCache(array $cache)
    {
        $encoded = json_encode($cache);

        if ($encoded === false || \Tools::strlen($encoded) > self::MAX_CACHE_SIZE) {
            return;
        }

        \Configuration::updateValue(self::CACHE_STORAGE_KEY, $encoded);
    }

    /**
     * @return array Breaker state with the `failures` and `opened_at` keys.
     */
    private static function readBreakerState()
    {
        $raw = (string) \Configuration::get(self::BREAKER_STORAGE_KEY);
        $state = $raw !== '' ? json_decode($raw, true) : null;

        return [
            'failures' => is_array($state) && isset($state['failures']) ? (int) $state['failures'] : 0,
            'opened_at' => is_array($state) && isset($state['opened_at']) ? (int) $state['opened_at'] : 0,
        ];
    }

    /**
     * @param array $state
     *
     * @return void
     */
    private static function writeBreakerState(array $state)
    {
        $encoded = json_encode($state);

        if ($encoded !== false) {
            \Configuration::updateValue(self::BREAKER_STORAGE_KEY, $encoded);
        }
    }
}
