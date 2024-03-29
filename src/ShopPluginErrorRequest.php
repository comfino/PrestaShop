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
     * @param ShopPluginError $shop_plugin_error
     * @param string $hash_key
     * @return bool
     */
    public function prepareRequest(ShopPluginError $shop_plugin_error, $hash_key)
    {
        $error_details_array = [
            'host' => $shop_plugin_error->host,
            'platform' => $shop_plugin_error->platform,
            'environment' => $shop_plugin_error->environment,
            'error_code' => $shop_plugin_error->errorCode,
            'error_message' => $shop_plugin_error->errorMessage,
            'api_request_url' => $shop_plugin_error->apiRequestUrl,
            'api_request' => $shop_plugin_error->apiRequest,
            'api_response' => $shop_plugin_error->apiResponse,
            'stack_trace' => $shop_plugin_error->stackTrace,
        ];

        if (($encoded_error_details = json_encode($error_details_array)) === false) {
            return false;
        }

        if (($error_details = gzcompress($encoded_error_details, 9)) === false) {
            return false;
        }

        $this->errorDetails = base64_encode($error_details);
        $this->hash = hash_hmac('sha256', $this->errorDetails, $hash_key);

        return true;
    }
}
