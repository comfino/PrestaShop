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

namespace Comfino\Extended\Api;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Api\Request\CancelOrder as CancelOrderRequest;
use Comfino\Api\Response\Base as BaseApiResponse;
use Comfino\Api\SerializerInterface;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;
use Comfino\Extended\Api\Request\NotifyShopPluginRemoval;
use Comfino\Extended\Api\Request\ReportShopPluginError;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Extended Comfino API client PHP 7.1+ compatible.
 */
class Client extends \Comfino\Api\Client
{
    public function __construct(
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ClientInterface $client,
        ?string $apiKey,
        $apiVersion = 1,
        ?SerializerInterface $serializer = null
    ) {
        parent::__construct($requestFactory, $streamFactory, $client, $apiKey, $apiVersion, $serializer ?? new JsonSerializer());
    }

    /**
     * Sends a plugin error report to the Comfino API.
     *
     * @param ShopPluginError $shopPluginError
     * @return bool
     */
    public function sendLoggedError($shopPluginError): bool
    {
        try {
            new BaseApiResponse(
                $this->sendRequest((new ReportShopPluginError($shopPluginError, $this->getUserAgent()))->setSerializer($this->serializer)),
                $this->serializer
            );
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }

    /**
     * Sends notification about plugin uninstallation.
     *
     * @return bool
     */
    public function notifyPluginRemoval(): bool
    {
        try {
            $this->sendRequest((new NotifyShopPluginRemoval())->setSerializer($this->serializer));
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }
}
