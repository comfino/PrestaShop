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

use Comfino\Configuration\ConfigManager;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class DebugLogger
{
    /** @var Common\Backend\DebugLogger */
    private static $debugLogger;

    public static function getLoggerInstance(): Common\Backend\DebugLogger
    {
        if (self::$debugLogger === null) {
            self::$debugLogger = Common\Backend\DebugLogger::getInstance(
                new JsonSerializer(),
                _PS_MODULE_DIR_ . COMFINO_MODULE_NAME . '/var/log/debug.log'
            );
        }

        return self::$debugLogger;
    }

    public static function logEvent(string $eventPrefix, string $eventMessage, ?array $parameters = null): void
    {
        if ((!isset($_COOKIE['COMFINO_SERVICE_SESSION']) || $_COOKIE['COMFINO_SERVICE_SESSION'] !== 'ACTIVE')
            && ConfigManager::isServiceMode()
        ) {
            return;
        }

        if (ConfigManager::isDebugMode()) {
            self::getLoggerInstance()->logEvent($eventPrefix, $eventMessage, $parameters);
        }
    }
}
