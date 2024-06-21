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

use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpoint\CacheInvalidate;
use Comfino\Common\Backend\RestEndpoint\Configuration;
use Comfino\Common\Backend\RestEndpoint\StatusNotification;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Order\StatusAdapter;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ApiService
{
    /** @var RestEndpointManager */
    private static $endpoint_manager;

    public static function init(\PaymentModule $module): void
    {
        self::$endpoint_manager = (new ApiServiceFactory())->createService(
            'PrestaShop',
            _PS_VERSION_,
            COMFINO_VERSION,
            [
                ConfigManager::getConfigurationValue('COMFINO_API_KEY'),
                ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY'),
            ]
        );

        self::$endpoint_manager->registerEndpoint(
            new StatusNotification(
                'transactionStatus',
                self::getControllerUrl($module, 'transactionstatus', [], false),
                StatusManager::getInstance(new StatusAdapter()),
                ConfigManager::getForbiddenStatuses(),
                ConfigManager::getIgnoredStatuses()
            )
        );

        self::$endpoint_manager->registerEndpoint(
            new Configuration(
                'configuration',
                self::getControllerUrl($module, 'configuration', [], false),
                ConfigManager::getInstance(),
                'PrestaShop',
                ...array_values(
                    ConfigManager::getEnvironmentInfo(['shop_version', 'plugin_version', 'database_version'])
                )
            )
        );

        self::$endpoint_manager->registerEndpoint(
            new CacheInvalidate(
                'cacheInvalidate',
                self::getControllerUrl($module, 'cacheinvalidate', [], false),
                CacheManager::getCachePool()
            )
        );
    }

    public static function getControllerUrl(
        \PaymentModule $module,
        string $controller_name,
        array $params = [],
        bool $with_lang_id = true
    ): string {
        $url = \Context::getContext()->link->getModuleLink($module->name, $controller_name, $params, true);

        return $with_lang_id ? $url : preg_replace('/&?id_lang=\d+&?/', '', $url);
    }

    public static function getEndpointUrl(string $endpointName): string
    {
        if (($endpoint = self::$endpoint_manager->getEndpointByName($endpointName)) !== null) {
            return $endpoint->getEndpointUrl();
        }

        return '';
    }

    public static function processRequest(string $endpointName): string
    {
        if (self::$endpoint_manager === null || empty(self::$endpoint_manager->getRegisteredEndpoints())) {
            http_response_code(503);

            return 'Endpoint manager not initialized.';
        }

        $response = self::$endpoint_manager->processRequest($endpointName);

        foreach ($response->getHeaders() as $header_name => $header_values) {
            foreach ($header_values as $header_value) {
                header(sprintf('%s: %s', $header_name, $header_value), false);
            }
        }

        $response_body = $response->getBody()->getContents();

        http_response_code($response->getStatusCode());

        return !empty($response_body) ? $response_body : $response->getReasonPhrase();
    }
}
