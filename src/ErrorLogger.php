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

use Comfino\ErrorLogger\StorageAdapter;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ErrorLogger
{
    /** @var Common\Backend\ErrorLogger */
    private static $errorLogger;

    public static function init(\PaymentModule $module): void
    {
        static $initialized = false;

        if (!$initialized) {
            self::$errorLogger = Common\Backend\ErrorLogger::getInstance(
                \Tools::getShopDomain(),
                'PrestaShop',
                'modules/' . $module->name,
                ConfigManager::getEnvironmentInfo(),
                ApiClient::getInstance(),
                new StorageAdapter()
            );

            self::$errorLogger->init();

            $initialized = true;
        }
    }

    public static function sendError(
        string $error_prefix,
        string $error_code,
        string $error_message,
        ?string $api_request_url = null,
        ?string $api_request = null,
        ?string $api_response = null,
        ?string $stack_trace = null
    ): void {
        self::$errorLogger->sendError(
            $error_prefix, $error_code, $error_message, $api_request_url, $api_request, $api_response, $stack_trace
        );
    }

    public static function logError(string $error_prefix, string $error_message): void
    {
        @file_put_contents(
            _PS_MODULE_DIR_ . 'comfino/payment_log.log',
            '[' . date('Y-m-d H:i:s') . "] $error_prefix: $error_message\n",
            FILE_APPEND
        );
    }

    public static function getErrorLog(int $num_lines): string
    {
        return self::$errorLogger->getErrorLog(_PS_MODULE_DIR_ . 'comfino/payment_log.log', $num_lines);
    }
}
