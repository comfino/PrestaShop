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

namespace Comfino\Extended\Api\Request;

use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Request;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;

/**
 * Shop plugin error reporting request.
 */
class ReportShopPluginError extends Request
{
    /**
     * @readonly
     * @var \Comfino\Extended\Api\Dto\Plugin\ShopPluginError
     */
    private $shopPluginError;
    /**
     * @readonly
     * @var string
     */
    private $hashKey;
    public function __construct(ShopPluginError $shopPluginError, string $hashKey)
    {
        $this->shopPluginError = $shopPluginError;
        $this->hashKey = $hashKey;
        $this->setRequestMethod('POST');
        $this->setApiEndpointPath('log-plugin-error');
    }

    protected function prepareRequestBody(): ?array
    {
        $errorDetailsArray = [
            'host' => $this->shopPluginError->host,
            'platform' => $this->shopPluginError->platform,
            'environment' => $this->shopPluginError->environment,
            'error_code' => $this->shopPluginError->errorCode,
            'error_message' => $this->shopPluginError->errorMessage,
            'api_request_url' => $this->shopPluginError->apiRequestUrl,
            'api_request' => $this->shopPluginError->apiRequest,
            'api_response' => $this->shopPluginError->apiResponse,
            'stack_trace' => $this->shopPluginError->stackTrace,
        ];

        if (($errorDetails = gzcompress($this->serializer->serialize($errorDetailsArray), 9)) === false) {
            throw new RequestValidationError('Error report preparation failed.');
        }

        return [
            'error_details' => base64_encode($errorDetails),
            'hash' => hash_hmac('sha256', $errorDetails, $this->hashKey),
        ];
    }
}
