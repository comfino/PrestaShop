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

namespace Comfino\View;

use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Common\Frontend\ProductWidgetScriptHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\Main;
use Comfino\Update\UpdateManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class FrontendManager
{
    public static function getLocalScriptUrl(string $scriptFileName, bool $frontScript = true): string
    {
        $scriptDirectory = ($frontScript ? 'front' : 'admin');

        if (ConfigManager::useDevEnvVars() && ConfigManager::useUnminifiedScripts()) {
            $scriptFileName = str_replace('.min.js', '.js', $scriptFileName);

            if (!file_exists(_PS_MODULE_DIR_ . COMFINO_MODULE_NAME . "/views/js/$scriptDirectory/$scriptFileName")) {
                $scriptFileName = str_replace('.js', '.min.js', $scriptFileName);
            }
        } elseif (strpos($scriptFileName, '.min.') === false) {
            $scriptFileName = str_replace('.js', '.min.js', $scriptFileName);
        }

        return _MODULE_DIR_ . COMFINO_MODULE_NAME . "/views/js/$scriptDirectory/$scriptFileName";
    }

    /**
     * Base URL for assets served from the dedicated SDK CDN host (sdk.*).
     */
    public static function getSdkCdnBaseUrl(): string
    {
        if (ConfigManager::useDevEnvVars() && getenv('COMFINO_DEV_SDK_CDN_BASE_URL')) {
            return getenv('COMFINO_DEV_SDK_CDN_BASE_URL');
        }

        return ConfigManager::isSandboxMode() ? 'https://sdk.craty.pl' : 'https://sdk.comfino.pl';
    }

    /**
     * Renders the product-page widget config block consumed by the CDN product widget script (`comfino-prestashop-widget.min.js`).
     * Emits a `<script type="application/json" id="comfino-widget-config">` element whose JSON matches the SDK's
     * WidgetConfig contract; the deferred script reads it, imports the SDK, and calls sdk.bootstrapWidget().
     * This is the recommended replacement for the removed inline `/script` front-controller endpoint: the config block
     * is emitted directly into the product page via hookDisplayHeader, and the per-platform script is loaded with
     * registerJavascript — no per-request JS-generating controller.
     *
     * The config array is filtered against the shared `ProductWidgetScriptHelper::WIDGET_CONFIG_KEYS` allowlist
     * (also drops nulls) and JSON-encoded with the same defensive flags the SDK init helpers use, so any
     * admin-controlled string (selectors, product names in productCartDetails) cannot terminate the script tag,
     * escape the JSON string, or smuggle entity references.
     *
     * @param int|null $productId Current product id, or null when unavailable
     *
     * @return string The `<script type="application/json" id="comfino-widget-config">…</script>` block
     */
    public static function renderWidgetConfigElement(?int $productId): string
    {
        try {
            $settings = ConfigManager::getConfigurationValues(
                'widget_settings',
                [
                    'COMFINO_WIDGET_KEY',
                    'COMFINO_WIDGET_TARGET_SELECTOR',
                    'COMFINO_WIDGET_PRICE_SELECTOR',
                    'COMFINO_WIDGET_PRICE_ATTRIBUTE',
                    'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                    'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                    'COMFINO_WIDGET_TYPE',
                    'COMFINO_WIDGET_OFFER_TYPES',
                    'COMFINO_WIDGET_EMBED_METHOD',
                    'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS',
                    'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
                    'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
                ]
            );

            $variables = ConfigManager::getWidgetVariables($productId);

            // getWidgetVariables() emits the string literal 'null' for absent numeric fields; normalize to real null.
            $notNull = static function ($value) {
                return $value === 'null' ? null : $value;
            };

            $offerTypesValue = $settings['COMFINO_WIDGET_OFFER_TYPES'] ?? [];
            $offerTypesList = is_array($offerTypesValue) ? $offerTypesValue : explode(',', (string) $offerTypesValue);

            $offerTypes = array_values(array_filter(
                array_map('trim', $offerTypesList),
                static function (string $type): bool {
                    return $type !== '';
                }
            ));

            $config = [
                'sdkScriptUrl' => ConfigManager::getSdkScriptUrl(),
                'environment' => ConfigManager::isSandboxMode() ? 'sandbox' : 'production',
                'widgetKey' => $settings['COMFINO_WIDGET_KEY'] ?? null,
                'loggingToken' => $variables['LOGGING_TOKEN'] ?? null,
                'trackId' => $variables['TRACK_ID'] ?? null,
                'widgetTargetSelector' => $settings['COMFINO_WIDGET_TARGET_SELECTOR'] ?? null,
                'priceSelector' => $settings['COMFINO_WIDGET_PRICE_SELECTOR'] ?? null,
                'priceAttribute' => ($settings['COMFINO_WIDGET_PRICE_ATTRIBUTE'] ?? '') ?: null,
                'priceObserverSelector' => $settings['COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR'] ?: null,
                'priceObserverLevel' => (int) ($settings['COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'] ?? 0),
                'embedMethod' => $settings['COMFINO_WIDGET_EMBED_METHOD'] ?? null,
                'widgetType' => $settings['COMFINO_WIDGET_TYPE'] ?? null,
                'offerTypes' => $offerTypes !== [] ? $offerTypes : null,
                'showProviderLogos' => (bool) ($settings['COMFINO_WIDGET_SHOW_PROVIDER_LOGOS'] ?? false),
                'hasPriceInput' => false,
                'bannerCssUrl' => ($settings['COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL'] ?? '') ?: null,
                'calculatorCssUrl' => ($settings['COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL'] ?? '') ?: null,
                'price' => $notNull($variables['PRODUCT_PRICE'] ?? null),
                'productId' => $notNull($variables['PRODUCT_ID'] ?? null),
                'availableProductTypes' => $variables['AVAILABLE_PRODUCT_TYPES'] ?? null,
                'productCartDetails' => $variables['PRODUCT_CART_DETAILS'] ?? null,
                'language' => $variables['LANGUAGE'] ?? null,
                'currency' => $variables['CURRENCY'] ?? null,
                'shopEnvironment' => $variables['SHOP_ENVIRONMENT'] ?? null,
            ];

            // Drops nulls and anything outside WIDGET_CONFIG_KEYS, so omitted options fall through to the SDK / CDN-profile defaults.
            $config = ProductWidgetScriptHelper::buildConfig($config);

            $json = json_encode(
                $config,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                return '';
            }

            return '<script type="application/json" id="' . ProductWidgetScriptHelper::CONFIG_ELEMENT_ID . '">' . $json . '</script>';
        } catch (\Throwable $e) {
            self::processError('Widget config element', $e, null, null, null, '[ERROR]', OperationContext::WidgetRendering);
        }

        return '';
    }

    /**
     * Display an admin notice about the available GitHub version.
     *
     * @return string HTML content for update notice banner
     */
    public static function displayGithubVersionNotice(\Comfino $module): string
    {
        $dismissedVersion = \Configuration::get('COMFINO_UPDATE_NOTICE_DISMISSED');

        /* Read directly from UpdateManager (its own 24h-cached result) instead of a separate Configuration-backed
           cache, so this notice can never drift out of sync with the release info shown on the config page. */
        $updateInfo = UpdateManager::checkForUpdates();
        $githubVersion = $updateInfo['github_version'] ?? '';

        if (empty($updateInfo['update_available']) || empty($githubVersion)) {
            return '';
        }

        // Check if this version was already dismissed.
        if ($dismissedVersion === $githubVersion) {
            return '';
        }

        /* "What's new" HTML of the available release. Server-sanitized already; re-purified here with PrestaShop's
           HTML purifier, so the notice output stays safe per marketplace requirements. */
        $updateInfo['description_html'] = !empty($updateInfo['description_html'])
            ? \Tools::purifyHTML($updateInfo['description_html'])
            : '';

        // Render the notice template.
        return TemplateManager::renderModuleView(
            'update-notice',
            'admin',
            [
                'update_info' => $updateInfo,
                'module_url' => \Context::getContext()->link->getAdminLink('AdminModules') .
                    '&configure=' . $module->name . '&tab_module=' . $module->tab . '&module_name=' . $module->name .
                    '&active_tab=plugin_diagnostics',
                'dismiss_url' => \Context::getContext()->link->getModuleLink($module->name, 'updatedismiss', [], true),
            ]
        );
    }

    /**
     * Unified error processing method for handling exceptions consistently across the module.
     *
     * @param string $errorPrefix Short description of error context.
     * @param \Throwable $exception Exception to process.
     * @param int|null $httpStatus Optional HTTP status code to set in response.
     * @param string|null $userErrorMessage Optional custom user-friendly error message.
     *
     * @return array Array with 'title' (user error message) and 'language' (shop language code).
     */
    public static function processError(
        string $errorPrefix,
        \Throwable $exception,
        ?int $httpStatus = null,
        ?string $userErrorMessage = null,
        ?array $parameters = null,
        string $eventPrefix = '[ERROR]',
        string $context = OperationContext::Unknown
    ): array {
        DebugLogger::logEvent(
            $eventPrefix,
            $errorPrefix,
            array_merge(
                [
                    'exception' => get_class($exception),
                    'error_message' => $exception->getMessage(),
                    'error_code' => $exception->getCode(),
                    'error_file' => $exception->getFile(),
                    'error_line' => $exception->getLine(),
                    'error_trace' => $exception->getTraceAsString(),
                ],
                $parameters ?? []
            )
        );

        ErrorLogger::sendError(
            $exception,
            $context,
            (string) $exception->getCode(),
            $exception->getMessage(),
            $exception instanceof HttpErrorExceptionInterface ? $exception->getUrl() : null,
            $exception instanceof HttpErrorExceptionInterface ? $exception->getRequestBody() : null,
            $exception instanceof HttpErrorExceptionInterface ? $exception->getResponseBody() : null,
            $exception->getTraceAsString()
        );

        if (empty($userErrorMessage)) {
            $userErrorMessage = Main::translate(
                'There was a technical problem. Please try again in a moment and it should work!'
            );
        }

        if ($httpStatus !== null) {
            http_response_code($httpStatus);
        }

        return ['title' => $userErrorMessage, 'language' => \Context::getContext()->language->iso_code];
    }
}
