<?php

namespace Comfino;

use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpoint\Configuration;
use Comfino\Common\Backend\RestEndpoint\StatusNotification;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Order\StatusAdapter;

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
}
