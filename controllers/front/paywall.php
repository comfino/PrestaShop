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

use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\ApiClient;
use Comfino\Cache\StorageAdapter;
use Comfino\Common\Backend\CacheManager;
use Comfino\Common\Frontend\PaywallRenderer;
use Comfino\TemplateManager;
use Comfino\TemplateRenderer\ControllerRendererStrategy;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoPaywallModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        parent::postProcess();

        if (!($this->module instanceof Comfino) || !$this->module->active) {
            TemplateManager::renderControllerView($this, 'module_disabled', 'front');

            return;
        }

        $language = Context::getContext()->language->iso_code;
        $queryCriteria = new LoanQueryCriteria(0);

        echo (new PaywallRenderer(
            ApiClient::getInstance(),
            CacheManager::getInstance()->getCacheBucket("paywall_$language", new StorageAdapter("paywall_$language")),
            new ControllerRendererStrategy($this)
        ))->renderPaywall($queryCriteria);

        exit;
    }
}
