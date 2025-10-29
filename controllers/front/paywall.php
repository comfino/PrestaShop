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
use Comfino\Api\HttpErrorExceptionInterface;
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

        if (!Tools::isEmpty('priceModifier') && is_numeric(Tools::getValue('priceModifier'))) {
            $priceModifier = (int) filter_var(Tools::getValue('priceModifier'), FILTER_VALIDATE_INT);
        } else {
            $priceModifier = 0;
        }

        $loanAmount = (int) round(round($this->context->cart->getOrderTotal(), 2) * 100);

        $shopCart = OrderManager::getShopCart($this->context->cart, $priceModifier);
        $allowedProductTypes = SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            $shopCart
        );

        if ($allowedProductTypes === []) {
            // Filters active - all product types disabled.
            TemplateManager::renderControllerView($this, 'paywall-disabled', 'front');

            return;
        }

        DebugLogger::logEvent(
            '[PAYWALL]',
            'renderPaywall',
            [
                '$loanAmount' => $loanAmount,
                '$priceModifier' => $priceModifier,
                '$cartTotalValue' => $shopCart->getTotalValue(),
                '$allowedProductTypes' => $allowedProductTypes,
                '$shopCart' => $shopCart->getAsArray(),
            ]
        );

        $paywallRenderer = FrontendManager::getPaywallRenderer();
        $paywallUrl = ApiService::getControllerUrl('paywall', [], false);
        $templateVariables = [
            'language' => Context::getContext()->language->iso_code,
            'styles' => FrontendManager::registerExternalStyles($paywallRenderer->getStyles()),
            'scripts' => FrontendManager::registerExternalScripts($paywallRenderer->getScripts()),
            'shop_url' => Tools::getHttpHost(true),
        ];

        try {
            $paywallContents = ApiClient::getInstance()->getPaywall(
                new LoanQueryCriteria($loanAmount, null, null, $allowedProductTypes),
                $paywallUrl
            );

            $templateName = 'paywall';
            $templateVariables['paywall_hash'] = $paywallRenderer->getPaywallHash(
                $paywallContents->paywallBody,
                ConfigManager::getApiKey()
            );
            $templateVariables['frontend_elements'] = [
                'paywallBody' => $paywallContents->paywallBody,
                'paywallHash' => $paywallContents->paywallHash,
            ];
        } catch (\Throwable $e) {
            http_response_code($e instanceof HttpErrorExceptionInterface ? $e->getStatusCode() : 500);

            $templateVariables = array_merge($templateVariables, ApiClient::processApiError('Paywall endpoint', $e));
            $templateName = 'api-error';
        } finally {
            if (($apiRequest = ApiClient::getInstance()->getRequest()) !== null) {
                DebugLogger::logEvent(
                    '[PAYWALL_API_REQUEST]',
                    'renderPaywall',
                    [
                        '$paywallUrl' => $paywallUrl,
                        '$request' => $apiRequest->getRequestBody(),
                        '$templateVariables' => $templateVariables
                    ]
                );
            }
        }

        if (!COMFINO_PS_17) {
            // Exception for PrestaShop 1.6.x view rendering.
            exit(TemplateManager::renderModuleView($this->module, $templateName, 'front', $templateVariables));
        }

        TemplateManager::renderControllerView($this, $templateName, 'front', $templateVariables);
    }
}
