<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
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
