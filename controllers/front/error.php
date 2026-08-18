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
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/PaymentErrorCode.php';

use Comfino\PaymentErrorCode;

class ComfinoErrorModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        if (!($this->module instanceof Comfino)) {
            Tools::redirect('index.php?controller=order');

            return;
        }

        if (!Tools::getIsset('error_code')) {
            /* Nothing to report - the page is only ever reached through a redirect that carries a code. */
            Tools::redirect('index.php?controller=order');

            return;
        }

        $this->context->smarty->assign([
            'error' => $this->getErrorMessage((string) Tools::getValue('error_code')),
        ]);

        if (COMFINO_PS_17) {
            $this->setTemplate('module:comfino/views/templates/front/payment_error.tpl');
        } else {
            $this->setTemplate('payment_error_16.tpl');
        }
    }

    /**
     * Resolves an error code into a translated message. An unknown or missing code falls back to the generic
     * message rather than being echoed back to the customer.
     *
     * @param string $error_code
     *
     * @return string
     */
    private function getErrorMessage($error_code)
    {
        switch ($error_code) {
            case PaymentErrorCode::PAYMENT_REJECTED:
                return $this->module->l(
                    'The financing application could not be started. Please choose another payment method.',
                    'error'
                );

            case PaymentErrorCode::SERVICE_UNAVAILABLE:
                return $this->module->l(
                    'The payment service is temporarily unavailable. Please try again in a few minutes.',
                    'error'
                );

            case PaymentErrorCode::ORDER_CREATION:
            default:
                return $this->module->l(
                    'Your order could not be processed. Please contact the shop for assistance.',
                    'error'
                );
        }
    }
}
