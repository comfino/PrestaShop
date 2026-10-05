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

namespace Comfino\Api;

use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpoint\CacheInvalidate;
use Comfino\Common\Backend\RestEndpoint\Configuration;
use Comfino\Common\Backend\RestEndpoint\StatusNotification;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\Order\StatusAdapter;
use Comfino\PluginShared\CacheManager;
use Comfino\Telemetry\ShopEnvironmentReporter;
use Comfino\View\SettingsForm;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ApiService
{
    /**
     * Minimal length of an API key accepted as a request signature secret.
     *
     * The error reporting request builder already refuses to key a MAC with anything shorter
     * (ReportShopPluginError::MIN_HASH_KEY_LENGTH), so a value below this length cannot be a real Comfino API key.
     */
    private const MIN_API_KEY_LENGTH = 16;

    /** @var RestEndpointManager[] One manager per shop-context scope, keyed by the scope string. */
    private static $endpointManagers = [];

    /**
     * Removes API keys which must never be accepted as a request signature secret.
     *
     * An unconfigured key slot holds null or an empty string, and a signature calculated with such a value
     * collapses to a hash of the request body alone - something any caller can compute without knowing any
     * secret at all. Empty slots are therefore dropped before they can be used for signature verification.
     *
     * @param mixed[] $apiKeys
     *
     * @return string[]
     */
    public static function filterApiKeys(array $apiKeys): array
    {
        return array_values(
            array_filter(
                $apiKeys,
                static function ($apiKey): bool {
                    return is_string($apiKey) && trim($apiKey) !== '' && strlen($apiKey) >= self::MIN_API_KEY_LENGTH;
                }
            )
        );
    }

    public static function init(): void
    {
        // Endpoints are registered when the manager of a scope is created (see getEndpointManager()).
        self::getEndpointManager();
    }

    private static function registerEndpoints(RestEndpointManager $endpointManager): void
    {
        $endpointManager->registerEndpoint(
            new StatusNotification(
                'transactionStatus',
                self::getControllerUrl('transactionstatus', [], false),
                StatusManager::getInstance(new StatusAdapter(), ConfigManager::getCurrentScope()),
                ConfigManager::getForbiddenStatuses(),
                ConfigManager::getIgnoredStatuses()
            )
        );

        $endpointManager->registerEndpoint(
            new Configuration(
                'configuration',
                self::getControllerUrl('configuration', [], false),
                ConfigManager::getInstance(),
                DebugLogger::getLoggerInstance(),
                'PrestaShop',
                ...array_merge(
                    array_values(
                        ConfigManager::getEnvironmentInfo(
                            ['shop_version', 'plugin_version', 'plugin_build_ts', 'database_version']
                        )
                    ),
                    [SettingsForm::DEBUG_LOG_NUM_LINES], // $debugLogNumLines
                    [null], // $shopExtraVariables
                    [
                        static function (): ?array {
                            return ShopEnvironmentReporter::getReportArray();
                        },
                    ] // $shopEnvironmentReportProvider
                )
            )
        );

        $endpointManager->registerEndpoint(
            new CacheInvalidate(
                'cacheInvalidate',
                self::getControllerUrl('cacheinvalidate', [], false),
                CacheManager::getCachePool()
            )
        );
    }

    public static function getControllerUrl(
        string $controllerName,
        array $params = [],
        bool $withLangId = true
    ): string {
        $url = \Context::getContext()->link->getModuleLink(COMFINO_MODULE_NAME, $controllerName, $params, true);

        return $withLangId ? $url : preg_replace('/&?id_lang=\d+&?/', '', $url);
    }

    public static function getControllerPath(
        string $controllerName,
        array $params = [],
        bool $withLangId = true
    ): string {
        $controllerUrl = self::getControllerUrl($controllerName, $params, $withLangId);
        $controllerPath = parse_url($controllerUrl, PHP_URL_PATH);
        $controllerParams = parse_url($controllerUrl, PHP_URL_QUERY);

        return $controllerPath . (!empty($controllerParams) ? '?' . $controllerParams : '');
    }

    public static function getEndpointUrl(string $endpointName): string
    {
        if (($endpoint = self::getEndpointManager()->getEndpointByName($endpointName)) !== null) {
            return $endpoint->getEndpointUrl();
        }

        return '';
    }

    public static function getValidationKey(): string
    {
        return self::getEndpointManager()->getValidationKey();
    }

    public static function getCrSignature(string $requestData): string
    {
        return self::getEndpointManager()->getCrSignature($requestData);
    }

    public static function processRequest(string $endpointName): string
    {
        $endpointManager = self::getEndpointManager();

        if (ConfigManager::isDebugMode()) {
            $request = $endpointManager->getServerRequest();
            $requestBody = $request->getBody()->getContents();

            // Rewind, so the endpoint manager still reads the full body.
            $request->getBody()->rewind();

            DebugLogger::logEvent(
                '[REST API request]',
                'processRequest',
                [
                    '$endpointName' => $endpointName,
                    'METHOD' => $request->getMethod(),
                    'PARAMS' => $request->getQueryParams(),
                    'HEADERS' => $request->getHeaders(),
                    'BODY' => $requestBody,
                ]
            );
        }

        if (empty($endpointManager->getRegisteredEndpoints())) {
            http_response_code(503);

            return 'Endpoint manager not initialized.';
        }

        $response = $endpointManager->processRequest($endpointName);

        foreach ($response->getHeaders() as $headerName => $headerValues) {
            foreach ($headerValues as $headerValue) {
                header(sprintf('%s: %s', $headerName, $headerValue), false);
            }
        }

        $responseBody = $response->getBody()->getContents();

        http_response_code($response->getStatusCode());

        if (ConfigManager::isDebugMode() && $response->getStatusCode() !== 200) {
            DebugLogger::logEvent(
                '[REST API response]',
                'processRequest',
                [
                    '$endpointName' => $endpointName,
                    'RECEIVED-CR-SIGNATURE-FINGERPRINT' => $endpointManager->getReceivedCrSignatureFingerprint(),
                    'CALCULATED-CR-SIGNATURE-FINGERPRINT' => $endpointManager->getCalculatedCrSignature(),
                    'HEADERS' => $response->getHeaders(),
                    'STATUS' => $response->getStatusCode(),
                    'BODY' => $responseBody,
                ]
            );
        }

        return !empty($responseBody) ? $responseBody : $response->getReasonPhrase();
    }

    /**
     * Returns the endpoint manager of the active shop scope.
     *
     * Under Multistore, API keys are shop-scoped, so each scope gets its own manager built from its own keys. A single
     * cached manager would verify every shop's requests against the keys of whichever shop context came first.
     */
    private static function getEndpointManager(): RestEndpointManager
    {
        $scope = ConfigManager::getCurrentScope();

        if (!isset(self::$endpointManagers[$scope])) {
            $apiKeys = [ConfigManager::getConfigurationValue('COMFINO_API_KEY')];

            /* The test environment key is accepted only while the shop actually runs in sandbox mode. Accepting
               both keys at once doubles the signature verification surface without any functional gain, and it
               mirrors ConfigManager::getApiKey(), which selects a single key by the same flag. */
            if (ConfigManager::isSandboxMode()) {
                $apiKeys[] = ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY');
            }

            self::$endpointManagers[$scope] = (new ApiServiceFactory())->createService(
                'PrestaShop',
                _PS_VERSION_,
                COMFINO_VERSION,
                self::filterApiKeys($apiKeys),
                $scope
            );

            self::registerEndpoints(self::$endpointManagers[$scope]);
        }

        return self::$endpointManagers[$scope];
    }
}
