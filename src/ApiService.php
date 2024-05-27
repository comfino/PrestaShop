<?php

namespace Comfino;

use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpoint\Configuration;
use Comfino\Common\Backend\RestEndpoint\StatusNotification;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Order\StatusAdapter;
use Psr\Http\Message\ResponseInterface;
use Sunrise\Http\Factory\ResponseFactory;

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
                ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY')
            ]
        );

        self::$endpointManager->registerEndpoint(
            new StatusNotification(
                'transactionStatus',
                $module->context->link->getModuleLink($module->name, 'transactionstatus', [], true),
                StatusManager::getInstance(new StatusAdapter()),
                [],
                []
            )
        );

        self::$endpointManager->registerEndpoint(
            new Configuration(
                'configuration',
                $module->context->link->getModuleLink($module->name, 'configuration', [], true),
                ConfigManager::getInstance(),
                'PrestaShop',
                ...ConfigManager::getEnvironmentInfo(['shop_version', 'plugin_version', 'database_version'])
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
