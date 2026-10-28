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

namespace Comfino\Configuration;

use Comfino\Common\Shop\Order\StatusManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Validation of configuration values that can arrive from outside the admin form (the signed remote configuration
 * endpoint) and of values read back from storage. Invalid entries are dropped, never repaired.
 */
final class RemoteValueValidator
{
    public const MAX_CONNECT_TIMEOUT = 10;
    public const MAX_TRANSFER_TIMEOUT = 30;
    public const MAX_CONNECT_ATTEMPTS = 3;

    private const SHOP_STATUS_PATTERN = '/^(PS_OS_|COMFINO_)[A-Z0-9_]{1,64}$/';

    /**
     * @param mixed $statusMap
     *
     * @return array<string, string>
     */
    public static function filterStatusMap($statusMap): array
    {
        if (!is_array($statusMap)) {
            return [];
        }

        return array_filter(
            $statusMap,
            static function ($shopStatus, $comfinoStatus) {
                return is_string($comfinoStatus) && in_array($comfinoStatus, StatusManager::STATUSES, true) &&
                    is_string($shopStatus) && preg_match(self::SHOP_STATUS_PATTERN, $shopStatus) === 1;
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @param mixed $statuses
     *
     * @return string[]
     */
    public static function filterStatusList($statuses): array
    {
        if (!is_array($statuses)) {
            return [];
        }

        return array_values(array_unique(array_filter($statuses, static function ($status): bool {
            return is_string($status) && in_array($status, StatusManager::STATUSES, true);
        })));
    }

    /**
     * Clamps the transport options to the plugin bounds so the values survive the shared client's own normalization:
     * attempts are never 0 and the transfer timeout always stays above the connect timeout.
     *
     * @param mixed $connectTimeout
     * @param mixed $transferTimeout
     * @param mixed $connectAttempts
     *
     * @return int[] [connect timeout, transfer timeout, connect attempts]
     */
    public static function clampTransport($connectTimeout, $transferTimeout, $connectAttempts): array
    {
        $connectTimeout = max(1, min(self::MAX_CONNECT_TIMEOUT, (int) $connectTimeout));
        $transferTimeout = max($connectTimeout + 1, min(self::MAX_TRANSFER_TIMEOUT, (int) $transferTimeout));
        $connectAttempts = max(1, min(self::MAX_CONNECT_ATTEMPTS, (int) $connectAttempts));

        return [$connectTimeout, $transferTimeout, $connectAttempts];
    }

    /**
     * Whether the URL is acceptable as a custom CSS URL: absolute https:// only (no javascript:, data:, http:).
     * Empty means "not set" and is valid.
     *
     * @param mixed $url
     */
    public static function isValidCssUrl($url): bool
    {
        if (!is_string($url) || trim($url) === '') {
            return true;
        }

        $url = trim($url);

        if (preg_match('/[\x00-\x20"\'<>\\\\]/', $url) === 1 || strncasecmp($url, 'https://', 8) !== 0) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '';
    }

    /**
     * Sanitizes option values about to be persisted. Values are in their storage form (arrays imploded, JSON encoded,
     * as ConfigurationManager::persist() hands them over), so the checks are applied on the decoded value.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function sanitizeForStorage(array $options): array
    {
        foreach ($options as $name => $value) {
            switch ($name) {
                case 'COMFINO_STATUS_MAP':
                    $decoded = is_string($value) ? json_decode($value, true) : $value;

                    if (!is_array($decoded)) {
                        unset($options[$name]);
                    } else {
                        $options[$name] = is_string($value)
                            ? json_encode(self::filterStatusMap($decoded))
                            : self::filterStatusMap($decoded);
                    }

                    break;

                case 'COMFINO_IGNORED_STATUSES':
                case 'COMFINO_FORBIDDEN_STATUSES':
                    $list = is_string($value) ? array_filter(array_map('trim', explode(',', $value)), 'strlen') : $value;
                    $filtered = self::filterStatusList($list);
                    $options[$name] = is_string($value) ? implode(',', $filtered) : $filtered;

                    break;

                case 'COMFINO_API_CONNECT_TIMEOUT':
                case 'COMFINO_API_TIMEOUT':
                case 'COMFINO_API_CONNECT_NUM_ATTEMPTS':
                case 'COMFINO_PROD_CAT_CACHE_TTL':
                    $options[$name] = max(0, (int) $value);

                    break;

                case 'COMFINO_PAYWALL_CUSTOM_CSS_URL':
                case 'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL':
                case 'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL':
                    if (!self::isValidCssUrl($value)) {
                        unset($options[$name]);
                    }

                    break;
            }
        }

        return $options;
    }
}
