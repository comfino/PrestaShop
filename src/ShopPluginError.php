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
