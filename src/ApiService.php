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
    private static $endpointManager;

    public static function init(\PaymentModule $module): void
    {
        self::$endpointManager = (new ApiServiceFactory())->createService(
            'PrestaShop',
            _PS_VERSION_,
            COMFINO_VERSION,
            [
                ConfigManager::getConfigurationValue('COMFINO_API_KEY'),
                ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY'),
            ]
        );

        self::$endpointManager->registerEndpoint(
            new StatusNotification(
                'transactionStatus',
                \Context::getContext()->link->getModuleLink($module->name, 'transactionstatus', [], true),
                StatusManager::getInstance(new StatusAdapter()),
                [],
                []
            )
        );

        self::$endpointManager->registerEndpoint(
            new Configuration(
                'configuration',
                \Context::getContext()->link->getModuleLink($module->name, 'configuration', [], true),
                ConfigManager::getInstance(),
                'PrestaShop',
                ...array_values(
                    ConfigManager::getEnvironmentInfo(['shop_version', 'plugin_version', 'database_version'])
                )
            )
        );
    }

    public static function processRequest(): string
    {
        if (self::$endpointManager === null || empty(self::$endpointManager->getRegisteredEndpoints())) {
            http_response_code(503);

            return 'Endpoint manager not initialized.';
        }

        $response = self::$endpointManager->processRequest();

        foreach ($response->getHeaders() as $headerName => $headerValues) {
            foreach ($headerValues as $headerValue) {
                header(sprintf('%s: %s', $headerName, $headerValue), false);
            }
        }

        $responseBody = $response->getBody()->getContents();

        http_response_code($response->getStatusCode());

        return !empty($responseBody) ? $responseBody : $response->getReasonPhrase();
    }
}
