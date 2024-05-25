<?php

namespace Comfino;

use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpoint\StatusNotification;
use Comfino\Common\Backend\RestEndpointManager;

final class ApiService
{
    /** @var RestEndpointManager */
    private static $endpointManager;

    public static function init(): void
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

        self::$endpointManager->registerEndpoint(new StatusNotification());
    }
}
