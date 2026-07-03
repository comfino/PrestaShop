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

namespace Comfino\Platform;

use Comfino\Configuration\ConfigManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * PrestaShop implementation of the shared PlatformInfoInterface.
 *
 * Reads platform/shop metadata from PrestaShop globals (Context, Tools, Db) and the existing ConfigManager, so the
 * shop-environment builder can assemble a backend report. PHP 7.1 compatible (hand-written, not Rector-built).
 */
class PrestaShopPlatformInfo implements PlatformInfoInterface
{
    /**
     * @var array<string, string>
     */
    private $envInfo;

    public function __construct()
    {
        $this->envInfo = ConfigManager::getEnvironmentInfo([
            'plugin_version',
            'shop_version',
            'php_version',
            'database_version',
        ]);
    }

    public function getCode(): string
    {
        return 'PS';
    }

    public function getName(): string
    {
        return 'PrestaShop';
    }

    public function getVersion(): string
    {
        return (string) ($this->envInfo['shop_version'] ?? _PS_VERSION_);
    }

    public function getLanguage(): string
    {
        $context = \Context::getContext();

        return ($context !== null && $context->language !== null) ? (string) $context->language->iso_code : '';
    }

    public function getCurrency(): string
    {
        $context = \Context::getContext();

        return ($context !== null && $context->currency !== null) ? (string) $context->currency->iso_code : '';
    }

    public function getDomain(): string
    {
        return (string) \Tools::getShopDomain(false, true);
    }

    public function getDatabaseVersion(): string
    {
        return (string) ($this->envInfo['database_version'] ?? '');
    }

    public function getPhpVersion(): string
    {
        return (string) ($this->envInfo['php_version'] ?? PHP_VERSION);
    }

    public function getPluginVersion(): string
    {
        return (string) ($this->envInfo['plugin_version'] ?? (defined('COMFINO_VERSION') ? COMFINO_VERSION : ''));
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'shopName' => $this->getName(),
            'shopVersion' => $this->getVersion(),
            'shopLanguage' => $this->getLanguage(),
            'shopCurrency' => $this->getCurrency(),
            'shopDomain' => $this->getDomain(),
            'databaseVersion' => $this->getDatabaseVersion(),
            'phpVersion' => $this->getPhpVersion(),
            'pluginVersion' => $this->getPluginVersion(),
        ];
    }
}
