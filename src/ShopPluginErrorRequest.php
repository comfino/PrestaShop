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

final class ShopPluginErrorRequest
{
    /**
     * @var string
     */
    public $errorDetails;

    /**
     * @var string
     */
    public $hash;

    /**
     * @param ShopPluginError $shopPluginError
     * @param string $hashKey
     *
     * @return bool
     */
    public function prepareRequest(ShopPluginError $shopPluginError, $hashKey)
    {
        $errorDetailsArray = [
            'host' => $shopPluginError->host,
            'platform' => $shopPluginError->platform,
            'environment' => $shopPluginError->environment,
            'error_code' => $shopPluginError->errorCode,
            'error_message' => $shopPluginError->errorMessage,
            'api_request_url' => $shopPluginError->apiRequestUrl,
            'api_request' => $shopPluginError->apiRequest,
            'api_response' => $shopPluginError->apiResponse,
            'stack_trace' => $shopPluginError->stackTrace,
        ];

        if (($encodedErrorDetails = json_encode($errorDetailsArray)) === false) {
            return false;
        }

        if (($errorDetails = gzcompress($encodedErrorDetails, 9)) === false) {
            return false;
        }

        $this->errorDetails = base64_encode($errorDetails);
        $this->hash = hash_hmac('sha256', $this->errorDetails, $hashKey);

        return true;
    }
}
