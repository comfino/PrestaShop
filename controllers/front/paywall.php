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
use Comfino\Configuration\SettingsManager;
use Comfino\ErrorLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\Order\OrderManager;
use Comfino\View\FrontendManager;
use Comfino\View\TemplateManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoPaywallModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        ErrorLogger::init($this->module);

        parent::postProcess();

        if (!($this->module instanceof Comfino) || !$this->module->active) {
            TemplateManager::renderControllerView($this, 'module_disabled', 'front');

            return;
        }

        $loanAmount = (int) ($this->context->cart->getOrderTotal() * 100);
        $allowedProductTypes = SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            OrderManager::getShopCart($this->context->cart, $loanAmount)
        );

        if ($allowedProductTypes === []) {
            // Filters active - all product types disabled.
            TemplateManager::renderControllerView($this, 'paywall_disabled', 'front');

            return;
        }

        if (!Tools::isEmpty('priceModifier') && is_numeric(Tools::getValue('priceModifier'))) {
            $priceModifier = (float) Tools::getValue('priceModifier');

            if ($priceModifier > 0) {
                $loanAmount += ((int) ($priceModifier * 100));
            }
        }

        Main::debugLog(
            '[PAYWALL]',
            'renderPaywall - $loanAmount=' . $loanAmount . ', $allowedProductTypes=' . json_encode($allowedProductTypes)
        );

        echo FrontendManager::getPaywallRenderer($this->module)
            ->renderPaywall(new LoanQueryCriteria($loanAmount, null, null, $allowedProductTypes));

        exit;
    }
}
