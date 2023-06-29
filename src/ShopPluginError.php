<?php
/**
 * 2007-2023 PrestaShop
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
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

namespace Comfino;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ShopPluginError
{
    /**
     * @var string
     */
    public $host;

    /**
     * @var string
     */
    public $platform;

    /**
     * @var array
     */
    public $environment;

    /**
     * @var string
     */
    public $errorCode;

    /**
     * @var string
     */
    public $errorMessage;

    /**
     * @var string|null
     */
    public $apiRequestUrl;

    /**
     * @var string|null
     */
    public $apiRequest;

    /**
     * @var string|null
     */
    public $apiResponse;

    /**
     * @var string|null
     */
    public $stackTrace;

    /**
     * @param string $host
     * @param string $platform
     * @param array $environment
     * @param string $errorCode
     * @param string $errorMessage
     * @param string|null $apiRequestUrl
     * @param string|null $apiRequest
     * @param string|null $apiResponse
     * @param string|null $stackTrace
     */
    public function __construct(
        $host,
        $platform,
        $environment,
        $errorCode,
        $errorMessage,
        $apiRequestUrl = null,
        $apiRequest = null,
        $apiResponse = null,
        $stackTrace = null
    ) {
        $this->host = $host;
        $this->platform = $platform;
        $this->environment = $environment;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->apiRequestUrl = $apiRequestUrl;
        $this->apiRequest = $apiRequest;
        $this->apiResponse = $apiResponse;
        $this->stackTrace = $stackTrace;
    }
}
