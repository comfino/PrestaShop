<?php
/**
 * 2007-2023 PrestaShop
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
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';
require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

class ComfinoPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        ErrorLogger::init();

        parent::postProcess();

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
            if ($module['name'] === 'comfino') {
                $authorized = true;

                break;
            }
        }

        if (!$authorized) {
            exit($this->module->l('This payment method is not available.'));
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

        $order_confirmation = ComfinoApi::createOrder(
            $this->context->cart,
            $this->module->currentOrder,
            'index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id .
            '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key
        );

        $order = new Order($this->module->currentOrder);

        if (!is_array($order_confirmation) || !isset($order_confirmation['applicationUrl'])) {
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            $order->save();

            ErrorLogger::sendError(
                'Order creation error', 0, 'Wrong Comfino API response.',
                $_SERVER['REQUEST_URI'],
                ComfinoApi::getLastRequestBody(),
                is_array($order_confirmation) ? json_encode($order_confirmation) : ComfinoApi::getLastResponseBody()
            );

            Tools::redirect($this->context->link->getModuleLink(
                $this->module->name,
                'error',
                [
                    'error' => is_array($order_confirmation) && isset($order_confirmation['errors'])
                        ? implode(',', $order_confirmation['errors'])
                        : 'Order creation error.',
                ],
                true
            ));
        }

        OrdersList::createOrder(
            [
                'id_comfino' => $order_confirmation['externalId'],
                'id_customer' => $cart->id_customer,
                'order_status' => $order_confirmation['status'],
                'legalize_link' => isset($order_confirmation['_links']['legalize'])
                    ? $order_confirmation['_links']['legalize']['href']
                    : '',
                'self_link' => $order_confirmation['_links']['self']['href'],
                'cancel_link' => $order_confirmation['_links']['cancel']['href'],
            ]
        );

        Tools::redirect($order_confirmation['applicationUrl']);
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
