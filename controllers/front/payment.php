<?php
/**
 * 2007-2021 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author PrestaShop SA <contact@prestashop.com>
 * @copyright  2007-2021 PrestaShop SA
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version  Release: $Revision$
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    return;
}

require_once("." . _MODULE_DIR_ . "comfino/src/Api.php");
require_once("." . _MODULE_DIR_ . "comfino/models/OrdersList.php");

class ComfinoPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (!($this->module instanceof Comfino)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cart = $this->context->cart;

        if ($cart->id_customer == 0 ||
            $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 ||
            !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cookie = Context::getContext()->cookie;
        if (!$cookie->loan_amount || !$cookie->loan_type || !$cookie->loan_term) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $address = $cart->getAddressCollection();
        if (!$address[$cart->id_address_delivery]->phone) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        // Check that this payment option is still available in case the customer changed his
        // address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'comfino') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->l('This payment method is not available.'));
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $this->module->validateOrder(
            (int)$cart->id,
            (int)Configuration::get('COMFINO_CREATED'),
            $total,
            $this->module->displayName,
            null,
            "",
            (int)$currency->id,
            false,
            $customer->secure_key
        );

        $url = 'index.php?controller=order-confirmation&id_cart=' . (int)$cart->id .
            '&id_module=' . (int)$this->module->id .
            '&id_order=' . $this->module->currentOrder .
            '&key=' . $customer->secure_key;
        $orderConfirmation = json_decode(
            ComfinoApi::createOrder($this->context->cart, $this->module->currentOrder, $url),
            true
        );

        $order = new Order($this->module->currentOrder);

        if (!isset($orderConfirmation['applicationUrl'])) {
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            $order->save();

            Tools::redirect($this->context->link->getModuleLink(
                $this->module->name,
                'error',
                ['error' => implode(',', $orderConfirmation['errors'])],
                true
            ));
        }

        OrdersList::createOrder(
            [
                'id_comfino' => $orderConfirmation['externalId'],
                'id_customer' => $cart->id_customer,
                'order_status' => $orderConfirmation['status'],
                'legalize_link' => $orderConfirmation['_links']['legalize']['href'],
                'self_link' => $orderConfirmation['_links']['self']['href'],
                'cancel_link' => $orderConfirmation['_links']['cancel']['href']
            ]
        );

        Tools::redirect($orderConfirmation['applicationUrl']);
    }
}
