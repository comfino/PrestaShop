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
use Comfino\Common\Frontend\WidgetSdkInitScriptHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
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

    public static function getExternalResourcesBaseUrl(): string
    {
        if (ConfigManager::useDevEnvVars() && getenv('COMFINO_DEV_STATIC_RESOURCES_BASE_URL')) {
            return getenv('COMFINO_DEV_STATIC_RESOURCES_BASE_URL');
        }

        return ConfigManager::isSandboxMode() ? 'https://widget.craty.pl' : 'https://widget.comfino.pl';
    }

    /**
     * Base URL for assets served from the dedicated SDK CDN host (sdk.*).
     * COMFINO_DEV_STATIC_RESOURCES_BASE_URL overrides this for local dev, same as getExternalResourcesBaseUrl().
     */
    public static function getSdkCdnBaseUrl(): string
    {
        if (ConfigManager::useDevEnvVars() && getenv('COMFINO_DEV_STATIC_RESOURCES_BASE_URL')) {
            return getenv('COMFINO_DEV_STATIC_RESOURCES_BASE_URL');
        }

        return ConfigManager::isSandboxMode() ? 'https://sdk.craty.pl' : 'https://sdk.comfino.pl';
    }

    public static function getExternalScriptUrl(string $scriptFileName): string
    {
        if (empty($scriptFileName)) {
            return '';
        }

        if (ConfigManager::useDevEnvVars() && ConfigManager::useUnminifiedScripts()) {
            $scriptFileName = str_replace('.min.js', '.js', $scriptFileName);
        } elseif (strpos($scriptFileName, '.min.') === false) {
            $scriptFileName = str_replace('.js', '.min.js', $scriptFileName);
        }

        if (ConfigManager::isSandboxMode()) {
            $scriptPath = trim(ConfigManager::getConfigurationValue('COMFINO_JS_DEV_PATH', ''), '/');

            if (strpos($scriptPath, '..') !== false) {
                $scriptPath = trim(ConfigManager::getDefaultValue('COMFINO_JS_DEV_PATH'), '/');
            }
        } else {
            $scriptPath = trim(ConfigManager::getConfigurationValue('COMFINO_JS_PROD_PATH', ''), '/');

            if (strpos($scriptPath, '..') !== false) {
                $scriptPath = trim(ConfigManager::getDefaultValue('COMFINO_JS_PROD_PATH'), '/');
            }
        }

        if (!empty($scriptPath)) {
            $scriptPath = "/$scriptPath";
        }

        return self::getExternalResourcesBaseUrl() . "$scriptPath/$scriptFileName";
    }

    public static function getExternalStyleUrl(string $styleFileName): string
    {
        if (empty($styleFileName)) {
            return '';
        }

        if (ConfigManager::isSandboxMode()) {
            $stylePath = trim(ConfigManager::getConfigurationValue('COMFINO_CSS_DEV_PATH', 'css'), '/');

            if (strpos($stylePath, '..') !== false) {
                $stylePath = trim(ConfigManager::getDefaultValue('COMFINO_CSS_DEV_PATH'), '/');
            }
        } else {
            $stylePath = trim(ConfigManager::getConfigurationValue('COMFINO_CSS_PROD_PATH', 'css'), '/');

            if (strpos($stylePath, '..') !== false) {
                $stylePath = trim(ConfigManager::getDefaultValue('COMFINO_CSS_PROD_PATH'), '/');
            }
        }

        if (!empty($stylePath)) {
            $stylePath = "/$stylePath";
        }

        return self::getExternalResourcesBaseUrl() . "$stylePath/$styleFileName";
    }

    /**
     * @param string[] $scripts
     *
     * @return string[]
     */
    public static function registerExternalScripts(array $scripts): array
    {
        $registeredScripts = [];

        foreach ($scripts as $scriptName) {
            $scriptId = 'comfino-script-' . str_replace('.', '-', strtolower(pathinfo($scriptName, PATHINFO_FILENAME)));
            $registeredScripts[$scriptId] = self::getExternalScriptUrl($scriptName);
        }

        return $registeredScripts;
    }

    /**
     * @param string[] $styles
     *
     * @return string[]
     */
    public static function registerExternalStyles(array $styles): array
    {
        $registeredStyles = [];

        foreach ($styles as $styleName) {
            $styleId = 'comfino-style-' . str_replace('.', '-', strtolower(pathinfo($styleName, PATHINFO_FILENAME)));
            $registeredStyles[$styleId] = self::getExternalStyleUrl($styleName);
        }

        return $registeredStyles;
    }

    public static function renderWidgetInitCode(?int $productId): string
    {
        $serializer = new JsonSerializer();

        try {
            $widgetParams = array_combine(
                [
                    'WIDGET_KEY',
                    'WIDGET_TARGET_SELECTOR',
                    'WIDGET_PRICE_SELECTOR',
                    'WIDGET_PRICE_OBSERVER_SELECTOR',
                    'WIDGET_PRICE_OBSERVER_LEVEL',
                    'WIDGET_TYPE',
                    'OFFER_TYPES',
                    'EMBED_METHOD',
                    'SHOW_PROVIDER_LOGOS',
                    'CUSTOM_BANNER_CSS_URL',
                    'CUSTOM_CALCULATOR_CSS_URL',
                ],
                array_map(
                    static function ($optionValue) use ($serializer) {
                        return is_array($optionValue) ? $serializer->serialize($optionValue) : $optionValue;
                    },
                    ConfigManager::getConfigurationValues(
                        'widget_settings',
                        [
                            'COMFINO_WIDGET_KEY',
                            'COMFINO_WIDGET_TARGET_SELECTOR',
                            'COMFINO_WIDGET_PRICE_SELECTOR',
                            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                            'COMFINO_WIDGET_TYPE',
                            'COMFINO_WIDGET_OFFER_TYPES',
                            'COMFINO_WIDGET_EMBED_METHOD',
                            'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS',
                            'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
                            'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
                        ]
                    )
                )
            );

            // New SDK-required params with no direct plugin setting yet.
            $widgetParams['ENVIRONMENT'] = ConfigManager::isSandboxMode() ? 'sandbox' : 'production';
            $widgetParams['HAS_PRICE_INPUT'] = false;

            return WidgetSdkInitScriptHelper::renderWidgetInitScript(
                ConfigManager::getCurrentWidgetCode($productId),
                $widgetParams,
                ConfigManager::getWidgetVariables($productId)
            );
        } catch (\Throwable $e) {
            self::processError('Widget script endpoint', $e);
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
        string $eventPrefix = '[ERROR]'
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
            $errorPrefix,
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
