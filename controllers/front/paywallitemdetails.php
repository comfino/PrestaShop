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
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\Order\OrderManager;
use Comfino\Shop\Order\Cart;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoPaywallItemDetailsModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        ErrorLogger::init();

        parent::postProcess();

        header('Content-Type: application/json');

        $serializer = new JsonSerializer();

        $loanAmount = (int) round(round($this->context->cart->getOrderTotal(), 2) * 100);
        $loanTypeSelected = Tools::getValue('loanTypeSelected');
        $loadProductCategories = (Tools::getValue('reqProdCat') === 'yes');
        $shopCart = OrderManager::getShopCart($this->context->cart, $loanAmount, $loadProductCategories);

        DebugLogger::logEvent(
            '[PAYWALL_ITEM_DETAILS]',
            'getPaywallItemDetails',
            [
                '$loanAmount' => $loanAmount,
                '$loanTypeSelected' => $loanTypeSelected,
                '$loadProductCategories' => $loadProductCategories,
                '$shopCart' => $shopCart->getAsArray(),
            ]
        );

        try {
            $paywallItemDetails = ApiClient::getInstance()->getPaywallItemDetails(
                $loanAmount,
                LoanTypeEnum::from($loanTypeSelected),
                new Cart(
                    $shopCart->getCartItems(),
                    $shopCart->getTotalValue(),
                    $shopCart->getDeliveryCost(),
                    $shopCart->getDeliveryNetCost(),
                    $shopCart->getDeliveryTaxRate(),
                    $shopCart->getDeliveryTaxValue()
                )
            );
        } catch (\Throwable $e) {
            http_response_code($e instanceof HttpErrorExceptionInterface ? $e->getStatusCode() : 500);

            exit($serializer->serialize(
                ApiClient::processApiError('Paywall item details endpoint', $e)['error_details']
            ));
        } finally {
            if (($apiRequest = ApiClient::getInstance()->getRequest()) !== null) {
                DebugLogger::logEvent(
                    '[PAYWALL_ITEM_DETAILS_API_REQUEST]',
                    'getPaywallItemDetails',
                    ['$request' => $apiRequest->getRequestBody()]
                );
            }
        }

        exit($serializer->serialize([
            'listItemData' => $paywallItemDetails->listItemData,
            'productDetails' => $paywallItemDetails->productDetails,
        ]));
    }
}
