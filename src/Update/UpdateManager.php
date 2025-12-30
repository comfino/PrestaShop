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

use Comfino\PluginShared\CacheManager;
use ComfinoExternal\Psr\Cache\InvalidArgumentException;
use ComfinoExternal\Psr\Http\Message\ResponseInterface;
use ComfinoExternal\Sunrise\Http\Client\Curl\Client;
use ComfinoExternal\Sunrise\Http\Factory\RequestFactory;
use ComfinoExternal\Sunrise\Http\Factory\ResponseFactory;
use ComfinoExternal\Sunrise\Http\Factory\StreamFactory;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Manages automatic updates from GitHub releases.
 *
 * This class handles:
 * - Checking for new versions on GitHub.
 * - Downloading release packages.
 * - Extracting and installing updates.
 * - Running upgrade scripts.
 * - Verifying update integrity.
 */
class UpdateManager
{
    private const GITHUB_REPOSITORY = 'comfino/PrestaShop';
    private const GITHUB_URL = 'https://github.com/' . self::GITHUB_REPOSITORY;
    private const GITHUB_API_URL = 'https://api.github.com/repos/' . self::GITHUB_REPOSITORY;
    private const CACHE_KEY = 'comfino_github_version_check';
    private const CACHE_TTL = 86400; // 24 hours
    private const CONNECT_TIMEOUT = 5;
    private const TRANSFER_TIMEOUT = 10;

    /**
     * Check for available updates on GitHub.
     *
     * @return array{
     *     update_available: bool,
     *     current_version: string,
     *     github_version?: string,
     *     download_url?: string,
     *     release_notes_url?: string,
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
     * Fetch latest release information from GitHub API.
     *
     * @return array Release information or error
     */
    private static function fetchLatestRelease(): array
    {
        $response = self::sendRequest(
            self::createClient(self::CONNECT_TIMEOUT, self::TRANSFER_TIMEOUT),
            'GET',
            self::GITHUB_API_URL . '/releases/latest',
            ['Accept' => 'application/vnd.github.v3+json']
        );

        if ($response->getStatusCode() !== 200) {
            return [
                'update_available' => false,
                'current_version' => COMFINO_VERSION,
                'error' => 'Failed to fetch release information from GitHub.',
                'checked_at' => time(),
            ];
        }

        $response->getBody()->rewind();
        $responseBody = $response->getBody()->getContents();
        $releaseInfo = json_decode($responseBody, true);

        if (!is_array($releaseInfo) || !isset($releaseInfo['tag_name'])) {
            return [
                'update_available' => false,
                'current_version' => COMFINO_VERSION,
                'error' => 'Invalid GitHub API response.',
                'checked_at' => time(),
            ];
        }

        $githubVersion = ltrim($releaseInfo['tag_name'], 'v');
        $updateAvailable = version_compare($githubVersion, COMFINO_VERSION, '>');

        return [
            'update_available' => $updateAvailable,
            'current_version' => COMFINO_VERSION,
            'github_version' => $githubVersion,
            'download_url' => $releaseInfo['zipball_url'] ?? null,
            'release_notes_url' => $releaseInfo['html_url'] ?? self::GITHUB_URL . '/releases',
            'checked_at' => time(),
        ];
    }

    private static function createClient($connectionTimeout, $transferTimeout, $options = []): Client
    {
        $clientOptions = [CURLOPT_CONNECTTIMEOUT => $connectionTimeout, CURLOPT_TIMEOUT => $transferTimeout];

        foreach ($options as $optionIdx => $valueValue) {
            $clientOptions[$optionIdx] = $valueValue;
        }

        return new Client(new ResponseFactory(), $clientOptions);
    }

    /**
     * @param string[] $headers
     */
    private static function sendRequest(
        Client $client,
        string $method,
        string $requestUri,
        array $headers = [],
        ?string $requestBody = null
    ): ResponseInterface {
        $requestFactory = new RequestFactory();
        $streamFactory = new StreamFactory();

        $request = $requestFactory->createRequest($method, $requestUri)
            ->withHeader('User-Agent', 'Comfino-PrestaShop-Plugin/' . COMFINO_VERSION);

        if (count($headers) > 0) {
            foreach ($headers as $headerName => $headerValue) {
                $request = $request->withHeader($headerName, $headerValue);
            }
        }

        if ($requestBody !== null) {
            $request = $request->withBody($streamFactory->createStream($requestBody));
        }

        return $client->sendRequest($request);
    }
}
