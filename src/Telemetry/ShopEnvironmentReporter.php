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
            $report = $builder->buildForBackendReport(
                self::resolveTestProductUrl(),
                ['opc_modules' => self::detectOpcModules()]
            );

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

            return $builder->buildReportArray(
                self::resolveTestProductUrl(),
                ['opc_modules' => self::detectOpcModules()]
            );
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
