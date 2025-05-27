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

use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\Order\OrderManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoProductDetailsModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        ErrorLogger::init();

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

        $shopCart = OrderManager::getShopCartFromProduct($product, true);
        $loanAmount = $shopCart->getTotalValue();

        DebugLogger::logEvent(
            '[PRODUCT_DETAILS]',
            'getShopCartFromProduct',
            [
                '$loanAmount' => $loanAmount,
                '$productId' => $productId,
                '$shopCart' => $shopCart->getAsArray(),
            ]
        );

        try {
            $response = $serializer->serialize($shopCart->getAsArray());
        } catch (Throwable $e) {
            ErrorLogger::sendError(
                $e,
                'Cart product details endpoint',
                $e->getCode(),
                $e->getMessage(),
                null,
                null,
                null,
                $e->getTraceAsString()
            );

            http_response_code(500);

            $response = $e->getMessage();
        }

        exit($response);
    }
}
