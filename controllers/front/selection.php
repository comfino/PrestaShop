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

use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\LoanSelection;
use Comfino\Order\OrderManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Remembers the shopper's current paywall selection (loan type and term) for their own cart. A checkout that submits
 * its payment form without the hidden loan inputs (custom themes) falls back to this value in payment.php.
 */
class ComfinoSelectionModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        parent::postProcess();

        header('Content-Type: application/json');

        $cart = $this->context->cart;

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' ||
            !($this->module instanceof Comfino) ||
            !$this->module->active ||
            !Validate::isLoadedObject($cart) ||
            !hash_equals(Tools::getToken(false), (string) Tools::getValue('token'))
        ) {
            http_response_code(403);

            exit(json_encode(['success' => false]));
        }

        try {
            $allowedProductTypes = SettingsManager::getAllowedProductTypes(
                ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
                OrderManager::getShopCart($cart, 0)
            );
        } catch (\Throwable $e) {
            $allowedProductTypes = null;
        }

        $selection = LoanSelection::validate(
            trim((string) Tools::getValue('comfino_loan_type')),
            (int) Tools::getValue('comfino_loan_term'),
            $allowedProductTypes
        );

        if ($selection === null) {
            http_response_code(400);

            exit(json_encode(['success' => false]));
        }

        LoanSelection::store($this->context->cookie, (int) $cart->id, $selection[0], $selection[1]);

        exit(json_encode(['success' => true]));
    }
}
