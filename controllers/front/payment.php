<?php
/**
 * 2007-2022 PrestaShop
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
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2022 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'comfino/src/Api.php';
require_once _PS_MODULE_DIR_.'comfino/models/OrdersList.php';

class ComfinoPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if (!($this->module instanceof Comfino)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 || !$this->module->active
        ) {
                Tools::redirect('index.php?controller=order&step=1');

                return;
        }

        $cookie = Context::getContext()->cookie;

        if (!$cookie->loan_amount || !$cookie->loan_type || !$cookie->loan_term) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $address = $cart->getAddressCollection();

        if (!$address[$cart->id_address_delivery]->phone && !$address[$cart->id_address_delivery]->phone_mobile) {
            $this->errors[] = $this->module->l(
                'No phone number in addresses found. Please fill value before choosing comfino payment option.'
            );

            if (COMFINO_PS_17) {
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            } else {
                $this->redirectWithNotificationsPs16('index.php?controller=order&step=1');
            }

            return;
        }

        /* Check that this payment option is still available in case the customer changed his
           address just before the end of the checkout process. */
        $authorized = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'comfino') {
                $authorized = true;

                break;
            }
        }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.'));
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $currency = $this->context->currency;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);

        $this->module->validateOrder(
            (int) $cart->id,
            (int) Configuration::get('COMFINO_CREATED'),
            $total,
            $this->module->displayName,
            null,
            '',
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        $createOrderResponse = ComfinoApi::createOrder(
            $this->context->cart,
            $this->module->currentOrder,
            'index.php?controller=order-confirmation&id_cart='.(int) $cart->id.'&id_module='.(int) $this->module->id.
            '&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key
        );

        $orderConfirmation = json_decode($createOrderResponse, true);
        $order = new Order($this->module->currentOrder);

        if (!is_array($orderConfirmation) || !isset($orderConfirmation['applicationUrl'])) {
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            $order->save();

            file_put_contents(
                '.'._MODULE_DIR_.'comfino/payment_log.log',
                '['.date('Y-m-d H:i:s').'] Order creation error - response: '.$createOrderResponse."\n",
                FILE_APPEND
            );

            Tools::redirect($this->context->link->getModuleLink(
                $this->module->name,
                'error',
                [
                    'error' => is_array($orderConfirmation) && isset($orderConfirmation['errors'])
                        ? implode(',', $orderConfirmation['errors'])
                        : 'Order creation error.'
                ],
                true
            ));
        }

        OrdersList::createOrder(
            [
                'id_comfino' => $orderConfirmation['externalId'],
                'id_customer' => $cart->id_customer,
                'order_status' => $orderConfirmation['status'],
                'legalize_link' => isset($orderConfirmation['_links']['legalize'])
                    ? $orderConfirmation['_links']['legalize']['href']
                    : '',
                'self_link' => $orderConfirmation['_links']['self']['href'],
                'cancel_link' => $orderConfirmation['_links']['cancel']['href']
            ]
        );

        Tools::redirect($orderConfirmation['applicationUrl']);
    }

    // FIXME Implement proper logic for PrestaShop 1.6.
    private function redirectWithNotificationsPs16()
    {
        $notifications = json_encode(['error' => $this->errors]);

        if (session_status() == PHP_SESSION_ACTIVE) {
            $_SESSION['notifications'] = $notifications;
        } else {
            setcookie('notifications', $notifications);
        }

        return call_user_func_array(['Tools', 'redirect'], func_get_args());
    }
}
