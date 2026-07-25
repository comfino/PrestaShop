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

namespace Comfino\Telemetry;

use Comfino\Api\ApiClient;
use Comfino\DebugLogger;
use Comfino\Frontend\PrestaShopShopEnvironmentBuilder;
use Comfino\Frontend\ThemeFamilyRules;
use Comfino\Platform\PrestaShopPlatformInfo;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Fire-and-forget service that reports the full shop environment to the Comfino API.
 *
 * Triggered after the module configuration is saved. Any failure is logged and swallowed — it must never impact the
 * merchant's config save, checkout, paywall, or widget functionality.
 */
final class ShopEnvironmentReporter
{
    /**
     * Builds and sends the current shop environment report to the Comfino API.
     *
     * @return bool True if the report was accepted, false on any failure.
     */
    public static function report(): bool
    {
        try {
            $builder = new PrestaShopShopEnvironmentBuilder(new PrestaShopPlatformInfo(), self::createThemeRules());
            $report = $builder->buildForBackendReport(self::resolveTestProductUrl(), self::buildMeta());

            $result = ApiClient::getInstance()->reportShopEnvironment($report);

            DebugLogger::logEvent(
                '[SHOP_ENVIRONMENT]',
                'ShopEnvironmentReporter::report: ' . ($result ? 'accepted' : 'rejected by API')
            );

            return $result;
        } catch (\Throwable $e) {
            DebugLogger::logEvent(
                '[SHOP_ENVIRONMENT]',
                'ShopEnvironmentReporter::report: failed',
                ['exceptionMessage' => $e->getMessage()]
            );

            return false;
        }
    }

    /**
     * Builds the current shop environment report as an array, for on-demand exposure via the configuration endpoint.
     *
     * @return array<string, mixed>|null The report array, or null on failure.
     */
    public static function getReportArray(): ?array
    {
        try {
            $builder = new PrestaShopShopEnvironmentBuilder(new PrestaShopPlatformInfo(), self::createThemeRules());

            return $builder->buildReportArray(self::resolveTestProductUrl(), self::buildMeta());
        } catch (\Throwable $e) {
            DebugLogger::logEvent(
                '[SHOP_ENVIRONMENT]',
                'ShopEnvironmentReporter::getReportArray: failed',
                ['exceptionMessage' => $e->getMessage()]
            );

            return null;
        }
    }

    /**
     * Builds the shop environment payload embedded in the widget init script's SHOP_ENVIRONMENT variable.
     *
     * Unlike report()/getReportArray(), this must never throw or block widget rendering, so failures degrade to an
     * empty array rather than propagating.
     *
     * @return array<string, mixed>
     */
    public static function getFrontendEnvironment(): array
    {
        try {
            $builder = new PrestaShopShopEnvironmentBuilder(new PrestaShopPlatformInfo(), self::createThemeRules());

            return $builder->buildForFrontend();
        } catch (\Throwable $e) {
            DebugLogger::logEvent(
                '[SHOP_ENVIRONMENT]',
                'ShopEnvironmentReporter::getFrontendEnvironment: failed',
                ['exceptionMessage' => $e->getMessage()]
            );

            return [];
        }
    }

    /**
     * Builds the custom metadata passed into the shop environment report: installed one-page-checkout modules known
     * to replace/alter PrestaShop's native checkout flow, and installed caching modules.
     *
     * The API's 'meta' field only accepts a flat map of scalar values (@see ReportShopEnvironment::sanitizeMeta() in
     * the shop-plugins-shared library) - any nested array value is silently dropped before the request is sent, so
     * detectOpcModules()'s/detectCacheModules()'s list-of-modules results are flattened to scalar keys here before
     * being returned.
     *
     * @return array<string, bool|int|string>
     */
    private static function buildMeta(): array
    {
        $opcModules = self::detectOpcModules();
        $cacheModules = self::detectCacheModules();

        return [
            'opc_modules_count' => count($opcModules),
            'opc_modules_names' => self::formatDetectedModules($opcModules),
            'cache_modules_count' => count($cacheModules),
            'cache_modules_names' => self::formatDetectedModules($cacheModules),
        ];
    }

    /**
     * Formats detected modules as "Name:Version" (or just "Name" when no version was resolved), joined with commas
     * (no spaces), for the flattened scalar-only 'meta' field (@see buildMeta()).
     *
     * @param array<int, array<string, mixed>> $modules
     */
    private static function formatDetectedModules(array $modules): string
    {
        $formatted = [];

        foreach ($modules as $module) {
            $formatted[] = empty($module['version']) ? $module['name'] : $module['name'] . ':' . $module['version'];
        }

        return implode(',', $formatted);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function detectOpcModules(): array
    {
        // Well-known OPC (One Page Checkout) modules for PrestaShop
        $knownOpcModules = [
            'onepagecheckoutps',         // One Page Checkout PS (popular paid module, PresTeamShop)
            'supercheckout',             // SuperCheckout (Knowband)
            'thecheckout',               // The Checkout (PrestaSmart)
            'fastcheckout',              // Fast Checkout
            'onepagecheckout',           // Generic/various free editions
            'gsopc',                     // GoSell OPC
            'klarnaofficial',            // Klarna — ships its own checkout flow
            'amazon_payments',           // Amazon Pay — replaces native checkout
            'paypalcheckout',            // PayPal Express Checkout
            'checkoutaddress',           // One Page Checkout & Address (free)
            'onepagecheckoutprestashop', // Another common naming variant
            'easycheckout',              // Easy Checkout (ST-themes)
            'expresscheckout',           // Express Checkout (various)
        ];

        $detected = [];

        foreach ($knownOpcModules as $moduleName) {
            try {
                if (!\Module::isInstalled($moduleName)) {
                    continue;
                }

                $instance = \Module::getInstanceByName($moduleName);
                $detected[] = [
                    'name' => $moduleName,
                    'version' => ($instance !== false && $instance !== null) ? $instance->version : null,
                    'active' => (bool) \Module::isEnabled($moduleName),
                ];
            } catch (\Throwable $e) {
                // Skip modules that can't be inspected.
            }
        }

        return $detected;
    }

    /**
     * Detects well-known PrestaShop caching modules, since aggressive full-page caching can serve stale paywall/widget
     * markup if not properly excluded by the merchant.
     *
     * PrestaShop's third-party module ecosystem for full-page/object caching is much smaller than WordPress's - most
     * caching here is handled at infrastructure level (Varnish, Nginx FastCGI cache, Redis) rather than via an
     * installable module. Detection is best-effort and matches by module technical name; this data is diagnostic
     * only and feeds into the shop environment report's 'meta' field.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function detectCacheModules(): array
    {
        $knownCacheModules = [
            'dreamcache', // DreamCache - Full Page Cache
            'litespeedcache', // LiteSpeed Cache
            'pagecacheultimate', // Page Cache Ultimate (JPresta)
            'jprestapagecache', // JPresta Page Cache (legacy identifier)
            'advancedpagecache', // Advanced Page Cache (Sunnytoo)
            'superspeed', // SuperSpeed (PrestaHero)
            'speedpack', // Speed Pack - Cache + Minify (Knowband)
        ];

        $detected = [];

        foreach ($knownCacheModules as $moduleName) {
            try {
                if (!\Module::isInstalled($moduleName)) {
                    continue;
                }

                $instance = \Module::getInstanceByName($moduleName);
                $detected[] = [
                    'name' => $moduleName,
                    'version' => ($instance !== false && $instance !== null) ? $instance->version : null,
                    'active' => (bool) \Module::isEnabled($moduleName),
                ];
            } catch (\Throwable $e) {
                // Skip modules that can't be inspected.
            }
        }

        return $detected;
    }

    private static function createThemeRules(): ThemeFamilyRules
    {
        $rules = new ThemeFamilyRules();

        $rules->register('classic', static function (array $themeChain): bool {
            foreach ($themeChain as $theme) {
                if (strpos($theme, 'classic') !== false) {
                    return true;
                }
            }

            return false;
        });

        return $rules;
    }

    /**
     * Resolves the URL of the first active product so the API may crawl it for selector auto-detection.
     *
     * @return string|null Product link, or null when no active product exists or resolution fails.
     */
    private static function resolveTestProductUrl(): ?string
    {
        try {
            $context = \Context::getContext();

            $idLang = ($context !== null && $context->language !== null)
                ? (int) $context->language->id
                : (int) \Configuration::get('PS_LANG_DEFAULT');

            $products = \Product::getProducts($idLang, 0, 1, 'id_product', 'ASC', false, true);

            if (empty($products) || $context === null || $context->link === null) {
                return null;
            }

            $link = $context->link->getProductLink((int) $products[0]['id_product']);

            return (is_string($link) && $link !== '') ? $link : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
