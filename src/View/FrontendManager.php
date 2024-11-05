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

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Common\Frontend\PaywallIframeRenderer;
use Comfino\Common\Frontend\PaywallRenderer;
use Comfino\Configuration\ConfigManager;
use Comfino\PluginShared\CacheManager;
use Comfino\TemplateRenderer\ModuleRendererStrategy;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class FrontendManager
{
    public static function getPaywallRenderer(\PaymentModule $module, $fullDocumentStructure = false): PaywallRenderer
    {
        $client = ApiClient::getInstance();
        $cookie = \Context::getContext()->cookie;

        if (isset($cookie->comfino_conn_attempt_idx)) {
            $connectAttemptIdx = $cookie->comfino_conn_attempt_idx;
        } else {
            $connectAttemptIdx = 1;
            $cookie->comfino_conn_attempt_idx = 1;
        }

        $client->resetClient(
            $client->calculateConnectionTimeout($connectAttemptIdx),
            $client->calculateTransferTimeout($connectAttemptIdx),
            1
        );

        return new PaywallRenderer(
            $client,
            CacheManager::getCachePool(),
            new ModuleRendererStrategy($module, $fullDocumentStructure),
            ApiService::getEndpointUrl('cacheInvalidate'),
            ApiService::getEndpointUrl('configuration'),
            \Tools::getShopDomainSsl(true)
        );
    }

    public static function getPaywallIframeRenderer(\PaymentModule $module): PaywallIframeRenderer
    {
        return new PaywallIframeRenderer(
            ApiClient::getInstance(),
            CacheManager::getCachePool(),
            new ModuleRendererStrategy($module),
            'PrestaShop',
            _PS_VERSION_,
            ApiService::getEndpointUrl('cacheInvalidate'),
            ApiService::getEndpointUrl('configuration')
        );
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
