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
use Comfino\Common\Frontend\PaywallIframeRenderer;
use Comfino\Common\Frontend\PaywallRenderer;
use Comfino\Common\Frontend\WidgetInitScriptHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\ErrorLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class FrontendManager
{
    public static function getPaywallRenderer(): PaywallRenderer
    {
        static $renderer = null;

        if ($renderer === null) {
            $renderer = new PaywallRenderer();
        }

        return $renderer;
    }

    public static function getPaywallIframeRenderer(): PaywallIframeRenderer
    {
        return new PaywallIframeRenderer();
    }

    public static function getLocalScriptUrl(string $scriptFileName, bool $frontScript = true): string
    {
        $scriptDirectory = ($frontScript ? 'front' : 'admin');

        if (ConfigManager::isDevEnv() && ConfigManager::useUnminifiedScripts()) {
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
        if (ConfigManager::isDevEnv() && getenv('COMFINO_DEV_STATIC_RESOURCES_BASE_URL')) {
            return getenv('COMFINO_DEV_STATIC_RESOURCES_BASE_URL');
        }

        return ConfigManager::isSandboxMode() ? 'https://widget.craty.pl' : 'https://widget.comfino.pl';
    }

    public static function getExternalScriptUrl(string $scriptFileName): string
    {
        if (empty($scriptFileName)) {
            return '';
        }

        if (ConfigManager::isDevEnv() && ConfigManager::useUnminifiedScripts()) {
            $scriptFileName = str_replace('.min.js', '.js', $scriptFileName);
        } elseif (strpos($scriptFileName, '.min.') === false) {
            $scriptFileName = str_replace('.js', '.min.js', $scriptFileName);
        }

        if (ConfigManager::isSandboxMode()) {
            $scriptPath = trim(ConfigManager::getConfigurationValue('COMFINO_JS_DEV_PATH'), '/');

            if (strpos($scriptPath, '..') !== false) {
                $scriptPath = trim(ConfigManager::getDefaultValue('COMFINO_JS_DEV_PATH'), '/');
            }
        } else {
            $scriptPath = trim(ConfigManager::getConfigurationValue('COMFINO_JS_PROD_PATH'), '/');

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
        try {
            $widgetVariables = ConfigManager::getWidgetVariables($productId);

            return WidgetInitScriptHelper::renderWidgetInitScript(
                ConfigManager::getCurrentWidgetCode($productId),
                array_combine(
                    [
                        'WIDGET_KEY',
                        'WIDGET_PRICE_SELECTOR',
                        'WIDGET_TARGET_SELECTOR',
                        'WIDGET_PRICE_OBSERVER_SELECTOR',
                        'WIDGET_PRICE_OBSERVER_LEVEL',
                        'WIDGET_TYPE',
                        'OFFER_TYPE',
                        'EMBED_METHOD',
                    ],
                    ConfigManager::getConfigurationValues(
                        'widget_settings',
                        [
                            'COMFINO_WIDGET_KEY',
                            'COMFINO_WIDGET_PRICE_SELECTOR',
                            'COMFINO_WIDGET_TARGET_SELECTOR',
                            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                            'COMFINO_WIDGET_TYPE',
                            'COMFINO_WIDGET_OFFER_TYPE',
                            'COMFINO_WIDGET_EMBED_METHOD',
                        ]
                    )
                ),
                $widgetVariables
            );
        } catch (\Throwable $e) {
            ErrorLogger::sendError(
                $e,
                'Widget script endpoint',
                $e->getCode(),
                $e->getMessage(),
                $e instanceof HttpErrorExceptionInterface ? $e->getUrl() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getRequestBody() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getResponseBody() : null,
                $e->getTraceAsString()
            );
        }

        return '';
    }

    public static function getConnectMaxNumAttempts(): int
    {
        return ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_NUM_ATTEMPTS', 3);
    }

    public static function getConnectAttemptIdx(): int
    {
        return \Context::getContext()->cookie->comfino_conn_attempt_idx ?? 1;
    }
}
