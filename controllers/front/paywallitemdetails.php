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

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\Main;
use Comfino\Order\OrderManager;
use Comfino\Shop\Order\Cart;
use Comfino\View\FrontendManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoPaywallItemDetailsModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        ErrorLogger::init($this->module);

        parent::postProcess();

        header('Content-Type: application/json');

        $serializer = new JsonSerializer();
        $loanTypeSelected = Tools::getValue('loanTypeSelected');

        $loanAmount = (int) ($this->context->cart->getOrderTotal() * 100);
        $shopCart = OrderManager::getShopCart($this->context->cart, $loanAmount);

        Main::debugLog(
            '[PAYWALL_ITEM_DETAILS]',
            'getPaywallItemDetails',
            ['$loanTypeSelected' => $loanTypeSelected]
        );

        $response = FrontendManager::getPaywallRenderer($this->module)
            ->getPaywallItemDetails(
                $loanAmount,
                LoanTypeEnum::from(Tools::getValue('loanTypeSelected')),
                new Cart($shopCart->getCartItems(), $shopCart->getTotalValue(), $shopCart->getDeliveryCost())
            );

        echo $serializer->serialize(
            ['listItemData' => $response->listItemData, 'productDetails' => $response->productDetails]
        );

        exit;
    }
}
