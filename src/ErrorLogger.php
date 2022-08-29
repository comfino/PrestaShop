<?php
/**
 * 2007-2022 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ErrorLogger
{
    /**
     * @param string $errorPrefix
     * @param string $errorMessage
     * @return void
     */
    public static function logError($errorPrefix, $errorMessage)
    {
        file_put_contents(
            _PS_MODULE_DIR_.'comfino/payment_log.log',
            "[".date('Y-m-d H:i:s')."] $errorPrefix: $errorMessage\n",
            FILE_APPEND
        );
    }

    /**
     * @param string $errorPrefix
     * @param string $errorCode
     * @param string $errorMessage
     * @param string|null $apiRequestUrl
     * @param string|null $apiRequest
     * @param string|null $apiResponse
     * @param string|null $stackTrace
     * @return void
     */
    public static function sendError(
        $errorPrefix,
        $errorCode,
        $errorMessage,
        $apiRequestUrl = null,
        $apiRequest = null,
        $apiResponse = null,
        $stackTrace = null
    )
    {
        $error = new ShopPluginError(
            Tools::getShopDomain(),
            'PrestaShop',
            [
                'plugin_version' => COMFINO_VERSION,
                'shop_version' => _PS_VERSION_,
                'symfony_version' => COMFINO_PS_17 && class_exists('\Symfony\Component\HttpKernel\Kernel')
                    ? \Symfony\Component\HttpKernel\Kernel::VERSION
                    : 'n/a',
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'],
                'server_name' => $_SERVER['SERVER_NAME'],
                'server_addr' => $_SERVER['SERVER_ADDR'],
                'database_version' => Db::getInstance()->getVersion()
            ],
            $errorCode,
            "$errorPrefix: $errorMessage",
            $apiRequestUrl,
            $apiRequest,
            $apiResponse,
            $stackTrace
        );

        if (!ComfinoApi::sendLoggedError($error)) {
            $requestInfo = [];

            if ($apiRequestUrl !== null) {
                $requestInfo[] = "API URL: $apiRequestUrl";
            }

            if ($apiRequest !== null) {
                $requestInfo[] = "API request: $apiRequest";
            }

            if ($apiResponse !== null) {
                $requestInfo[] = "API response: $apiResponse";
            }

            if (count($requestInfo)) {
                $errorMessage .= "\n".implode("\n", $requestInfo);
            }

            if ($stackTrace !== null) {
                $errorMessage .= "\nStack trace: $stackTrace";
            }

            self::logError($errorPrefix, $errorMessage);
        }
    }

    public static function errorHandler(Exception $exception)
    {

    }

    public static function exceptionHandler()
    {

    }

    public static function init()
    {

    }

    public static function shutdown()
    {

    }
}
