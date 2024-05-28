<?php

namespace Comfino\Common\Backend\RestEndpoint;

use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;
use Psr\Http\Message\ServerRequestInterface;

final class Configuration extends RestEndpoint
{
    public function __construct(
        string $name,
        string $endpointUrl,
        private readonly ConfigurationManager $configurationManager,
        private readonly string $platformName,
        private readonly string $platformVersion,
        private readonly string $pluginVersion,
        private readonly string $databaseVersion
    ) {
        parent::__construct($name, $endpointUrl);

        $this->methods = ['GET', 'POST', 'PUT', 'PATCH'];
    }

    public function processRequest(ServerRequestInterface $serverRequest): ?array
    {
        if (!$this->endpointPathMatch($serverRequest)) {
            throw new InvalidEndpoint('Endpoint path does not match request path.');
        }

        if ($serverRequest->getMethod() === 'GET') {
            return [
                'shop_info' => [
                    'platform' => $this->platformName,
                    'platform_version' => $this->platformVersion,
                    'plugin_version' => $this->pluginVersion,
                    'symfony_version' => class_exists('\Symfony\Component\HttpKernel\Kernel')
                        ? \Symfony\Component\HttpKernel\Kernel::VERSION
                        : 'n/a',
                    'php_version' => PHP_VERSION,
                    'server_software' => $serverRequest->getServerParams()['SERVER_SOFTWARE'],
                    'server_name' => $serverRequest->getServerParams()['SERVER_NAME'],
                    'server_addr' => $serverRequest->getServerParams()['SERVER_ADDR'],
                    'database_version' => $this->databaseVersion,
                ],
                'shop_configuration' => $this->configurationManager->returnConfigurationOptions(),
            ];
        }

        $this->configurationManager->updateConfigurationOptions($serverRequest->getParsedBody());

        return null;
    }
}
