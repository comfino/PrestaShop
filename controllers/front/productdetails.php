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
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\Main;
use Comfino\Order\OrderManager;
use Comfino\Shop\Order\Cart;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoProductDetailsModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        ErrorLogger::init($this->module);

        parent::postProcess();

        header('Content-Type: application/json');

        $serializer = new JsonSerializer();

        if (!Tools::getIsset('product_id')) {
            exit;
        }

        $productId = Tools::getValue('product_id');
        $product = new Product($productId);

        if (!Validate::isLoadedObject($product)) {
            exit;
        }

        $loanTypeSelected = Tools::getValue('loanTypeSelected');
        $shopCart = OrderManager::getShopCartFromProduct($product, $loanTypeSelected === 'LEASING');
        $loanAmount = $shopCart->getTotalValue();

        Main::debugLog(
            '[PRODUCT_DETAILS]',
            'getFinancialProductDetails',
            [
                '$loanAmount' => $loanAmount,
                '$productId' => $productId,
                '$loanTypeSelected' => $loanTypeSelected,
                '$shopCart' => $shopCart->getAsArray(),
            ]
        );

        try {
            echo $serializer->serialize(
                ApiClient::getInstance()->getFinancialProductDetails(
                    new LoanQueryCriteria($loanAmount, null, LoanTypeEnum::from($loanTypeSelected)),
                    new Cart(
                        $shopCart->getCartItems(),
                        $shopCart->getTotalValue(),
                        $shopCart->getDeliveryCost(),
                        $shopCart->getDeliveryNetCost(),
                        $shopCart->getDeliveryTaxRate(),
                        $shopCart->getDeliveryTaxValue()
                    )
                )->financialProducts
            );
        } catch (Throwable $e) {
            ErrorLogger::sendError(
                'Product details endpoint',
                $e->getCode(),
                $e->getMessage(),
                $e instanceof HttpErrorExceptionInterface ? $e->getUrl() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getRequestBody() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getResponseBody() : null,
                $e->getTraceAsString()
            );

            http_response_code($e instanceof HttpErrorExceptionInterface ? $e->getStatusCode() : 500);

            echo $e->getMessage();
        }

        exit;
    }
}
