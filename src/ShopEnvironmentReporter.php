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

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';

/**
 * Builds the shop environment description in two flavours:
 *
 *  - getFrontendEnvironment(): browser-safe subset embedded in the paywall and widget bootstrap config,
 *  - report(): full fire-and-forget backend report sent after the module configuration is saved.
 *
 * Nothing here may ever break a config save, the paywall or the widget, so every failure degrades to an empty
 * payload / false return value.
 */
class ShopEnvironmentReporter
{
    /**
     * Browser-safe environment payload.
     *
     * @param array|null $page_context e.g. ['type' => 'checkout']
     *
     * @return array
     */
    public static function getFrontendEnvironment($page_context = null)
    {
        try {
            $context = \Context::getContext();

            $environment = [
                'platform' => 'prestashop',
                'platformName' => 'PrestaShop',
                'platformDomain' => \Tools::getShopDomain(false, true),
                'theme' => ['family' => self::detectThemeFamily()],
                'language' => $context->language->iso_code,
                'currency' => $context->currency->iso_code,
            ];

            if ($page_context !== null) {
                $environment['pageContext'] = $page_context;
            }

            return $environment;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Sends the full environment report to the API. Never throws.
     *
     * @return bool true when the report was accepted
     */
    public static function report()
    {
        try {
            return Api::reportShopEnvironment(self::buildReport());
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return array
     */
    private static function buildReport()
    {
        $context = \Context::getContext();
        $theme_code = self::detectThemeCode();
        $theme_family = self::detectThemeFamily();

        return [
            'platform' => 'prestashop',
            'platform_name' => 'PrestaShop',
            'platform_version' => _PS_VERSION_,
            'platform_edition' => null,
            'platform_domain' => \Tools::getShopDomain(false, true),
            'plugin_version' => COMFINO_VERSION,
            'theme' => [
                'code' => $theme_code,
                'family' => $theme_family,
                'parents' => [],
            ],
            'language' => $context->language->iso_code,
            'currency' => $context->currency->iso_code,
            'capabilities' => self::getCapabilities($theme_family),
            'test_product_url' => self::resolveTestProductUrl(),
            'meta' => [
                'php_version' => PHP_VERSION,
                'database_version' => self::getDatabaseVersion(),
            ],
        ];
    }

    /**
     * Frontend stack capabilities implied by the theme family, used by the API to pick a rendering strategy.
     *
     * @param string $theme_family
     *
     * @return array
     */
    private static function getCapabilities($theme_family)
    {
        $classic_stack = ['classic', 'storefront', 'blocks'];

        return [
            'knockout' => false,
            'alpine' => false,
            'tailwind' => false,
            'requirejs' => false,
            'jquery' => in_array($theme_family, $classic_stack, true),
        ];
    }

    /**
     * @return string
     */
    private static function detectThemeCode()
    {
        try {
            $context = \Context::getContext();

            if ($context->shop !== null && isset($context->shop->theme_name)) {
                return (string) $context->shop->theme_name;
            }

            /* PrestaShop 1.6 keeps the active theme in the shop's id_theme relation instead of a theme name. */
            if ($context->shop !== null && !empty($context->shop->id_theme) && class_exists('\Theme')) {
                $theme = new \Theme((int) $context->shop->id_theme);

                if (\Validate::isLoadedObject($theme)) {
                    return (string) $theme->directory;
                }
            }
        } catch (\Exception $e) {
            // Theme detection is diagnostic only - fall through to the empty code.
        }

        return '';
    }

    /**
     * @return string
     */
    private static function detectThemeFamily()
    {
        /* PrestaShop themes - stock and custom alike - all run the classic jQuery-based frontend stack;
           there is no second family to detect here, unlike on platforms with alternative frontends. */
        return 'classic';
    }

    /**
     * URL of the first active product, so the API may crawl it for price selector auto-detection.
     *
     * @return string|null
     */
    private static function resolveTestProductUrl()
    {
        try {
            $context = \Context::getContext();
            $id_lang = $context->language !== null
                ? (int) $context->language->id
                : (int) \Configuration::get('PS_LANG_DEFAULT');

            $products = \Product::getProducts($id_lang, 0, 1, 'id_product', 'ASC', false, true);

            if (empty($products) || $context->link === null) {
                return null;
            }

            $link = $context->link->getProductLink((int) $products[0]['id_product']);

            return (is_string($link) && $link !== '') ? $link : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return string
     */
    private static function getDatabaseVersion()
    {
        try {
            return (string) \Db::getInstance()->getValue('SELECT VERSION()');
        } catch (\Exception $e) {
            return '';
        }
    }
}