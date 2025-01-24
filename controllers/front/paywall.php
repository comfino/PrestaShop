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

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\View\FrontendManager;
use Comfino\View\TemplateManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoPaywallModuleFrontController extends ModuleFrontController
{
    public function initContent(): void
    {
        ErrorLogger::init();

        parent::initContent();

        if (!($this->module instanceof Comfino) || !$this->module->active) {
            TemplateManager::renderControllerView($this, 'module-disabled', 'front');

            return;
        }

        $loanAmount = (int) round(round($this->context->cart->getOrderTotal(), 2) * 100);
        $shopCart = OrderManager::getShopCart($this->context->cart, $loanAmount);
        $allowedProductTypes = SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            $shopCart
        );

        if ($allowedProductTypes === []) {
            // Filters active - all product types disabled.
            TemplateManager::renderControllerView($this, 'paywall-disabled', 'front');

            return;
        }

        if (!Tools::isEmpty('priceModifier') && is_numeric(Tools::getValue('priceModifier'))) {
            $priceModifier = (float) Tools::getValue('priceModifier');

            if ($priceModifier > 0) {
                $loanAmount += ((int) ($priceModifier * 100));
            }
        }

        /*$connectAttemptIdx = FrontendManager::getConnectAttemptIdx();
        $connectMaxNumAttempts = FrontendManager::getConnectMaxNumAttempts();

        if ($connectAttemptIdx > 1 && $connectAttemptIdx < $connectMaxNumAttempts) {
            $headMetaTags = [new HeadMetaTag(null, 'refresh', '3')];
        } else {
            $headMetaTags = null;
        }*/

        DebugLogger::logEvent(
            '[PAYWALL]',
            'renderPaywall',
            [
                '$loanAmount' => $loanAmount,
                '$allowedProductTypes' => $allowedProductTypes,
                '$shopCart' => $shopCart->getAsArray(),
                // '$connectAttemptIdx' => $connectAttemptIdx,
                // '$connectMaxNumAttempts' => $connectMaxNumAttempts,
            ]
        );

        $paywallRenderer = FrontendManager::getPaywallRenderer($this->module);
        $paywallContents = $paywallRenderer->getPaywall(
            new LoanQueryCriteria($loanAmount, null, null, $allowedProductTypes),
            ApiService::getEndpointUrl('paywall')
        );
        $templateVariables = [
            'language' => Context::getContext()->language->iso_code,
            'styles' => FrontendManager::registerExternalStyles($paywallRenderer->getStyles()),
            'scripts' => FrontendManager::registerExternalScripts($paywallRenderer->getScripts()),
            'shop_url' => Tools::getHttpHost(true),
            'paywall_hash' => $paywallRenderer->getPaywallHash($paywallContents->paywallBody, ConfigManager::getApiKey()),
            'frontend_elements' => [
                'paywallBody' => $paywallContents->paywallBody,
                'paywallHash' => $paywallContents->paywallHash,
            ],
        ];

        if (($apiRequest = ApiClient::getInstance()->getRequest()) !== null) {
            DebugLogger::logEvent(
                '[PAYWALL_API_REQUEST]',
                'renderPaywall',
                ['$request' => $apiRequest->getRequestBody(), '$templateVariables' => $templateVariables]
            );
        }

        if (!COMFINO_PS_17) {
            // Exception for PrestaShop 1.6.x view rendering.
            exit(TemplateManager::renderModuleView($this->module, 'paywall', 'front', $templateVariables));
        }

        TemplateManager::renderControllerView($this, 'paywall', 'front', $templateVariables);
    }
}
