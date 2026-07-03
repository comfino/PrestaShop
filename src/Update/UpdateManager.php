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

namespace Comfino\Update;

use Comfino\Api\ApiClient;
use Comfino\PluginShared\CacheManager;
use ComfinoExternal\Psr\Cache\InvalidArgumentException;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Manages update-availability checks for the plugin.
 *
 * The latest available release is resolved through the centralized Comfino release API
 * (GET /v1/plugin-releases/{platform}/latest) instead of polling GitHub directly. The API returns the release of the
 * line compatible with this shop's PHP and PrestaShop version (derived from the client User-Agent), so the version we
 * compare against is always installable here.
 */
class UpdateManager
{
    /** Base canonical platform slug polled on the Comfino release API; the API resolves the concrete line by User-Agent. */
    private const PLATFORM = 'prestashop';
    private const CACHE_KEY = 'comfino_github_version_check';
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Check for available updates via the Comfino release API.
     *
     * @return array{
     *     update_available: bool,
     *     current_version: string,
     *     github_version?: string,
     *     download_url?: string,
     *     release_notes_url?: string,
     *     description_html?: string,
     *     checked_at?: int,
     *     error?: string
     * }
     */
    public static function checkForUpdates(): array
    {
        $cacheManager = CacheManager::getCachePool();

        // Try to get cached result.
        $cacheItem = $cacheManager->getItem(self::CACHE_KEY);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        // Fetch latest release from GitHub API.
        $lastReleaseInfo = self::fetchLatestRelease();

        // Cache the result.
        $cacheItem->set($lastReleaseInfo);
        $cacheItem->expiresAfter(self::CACHE_TTL);

        $cacheManager->save($cacheItem);

        return $lastReleaseInfo;
    }

    /**
     * Force refresh of update information (bypassing cache).
     *
     * @return array Update information
     */
    public static function forceCheckForUpdates(): array
    {
        try {
            CacheManager::getCachePool()->deleteItem(self::CACHE_KEY);
        } catch (InvalidArgumentException $e) {
            // Ignore cache errors.
        }

        return self::checkForUpdates();
    }

    /**
     * Fetch the latest release information from the Comfino release API.
     *
     * @return array Release information or error
     */
    private static function fetchLatestRelease(): array
    {
        try {
            $release = ApiClient::getInstance()->getLatestPluginRelease(self::PLATFORM);
        } catch (\Throwable $e) {
            return [
                'update_available' => false,
                'current_version' => COMFINO_VERSION,
                'error' => 'Failed to fetch release information from Comfino API: ' . $e->getMessage(),
                'checked_at' => time(),
            ];
        }

        if ($release === null) {
            return [
                'update_available' => false,
                'current_version' => COMFINO_VERSION,
                'error' => 'No release information available from Comfino API.',
                'checked_at' => time(),
            ];
        }

        return [
            'update_available' => version_compare($release->version, COMFINO_VERSION, '>'),
            'current_version' => COMFINO_VERSION,
            'github_version' => $release->version,
            'download_url' => $release->downloadUrl,
            'release_notes_url' => $release->releaseUrl,
            'description_html' => $release->descriptionHtml,
            'checked_at' => time(),
        ];
    }
}
