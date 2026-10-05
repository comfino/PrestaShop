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

namespace Comfino\Tests;

use Comfino\Api\ApiService;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Common\Shop\Order\StatusApplicationContext;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;

/**
 * Resets the process-wide state that module and shared-library singletons keep between tests.
 */
final class TestState
{
    public static function reset()
    {
        /* Dropped first: ConfigurationManager persists modified options from its destructor, which would otherwise
           write into the freshly cleared configuration store. */
        self::setStatic(ConfigManager::class, 'configurationManagers', []);
        self::setStatic(ConfigurationManager::class, 'instances', []);
        self::setStatic(ApiService::class, 'endpointManagers', []);
        self::setStatic(StatusManager::class, 'instances', []);

        RestEndpointManager::reset();
        StatusApplicationContext::reset();

        \Configuration::$values = [];
        \Db::$queries = [];
        \Db::$valueResolver = null;
        \Shop::$featureActive = false;
        \Shop::$context = \Shop::CONTEXT_ALL;
        \Shop::$contextShopId = null;
        \Shop::$contextShopGroupId = null;
    }

    /**
     * @param mixed $value
     *
     * @throws \ReflectionException
     */
    private static function setStatic(string $className, string $propertyName, $value): void
    {
        $property = new \ReflectionProperty($className, $propertyName);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
