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

use Comfino\ApiClient;
use Comfino\Common\Backend\Factory\OrderFactory;
use Comfino\ErrorLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\OrderManager;
use Comfino\SettingsManager;
use Comfino\Shop\Order\Customer;
use Comfino\Shop\Order\Customer\Address;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        ApiClient::init();
        ErrorLogger::init($this->module);

        parent::postProcess();

        if (!($this->module instanceof Comfino) || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cart = $this->context->cart;

        if ($cart->id_customer === 0 || $cart->id_address_delivery === 0 || $cart->id_address_invoice === 0) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cookie = $this->context->cookie;

        if (!$cookie->loan_type || !$cookie->loan_term) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $addresses = $cart->getAddressCollection();

        if (!$addresses[$cart->id_address_delivery]->phone && !$addresses[$cart->id_address_delivery]->phone_mobile) {
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

        /* Check that this payment option is still available in case the customer changed his address just before
           the end of the checkout process. */
        $comfino_is_available = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === 'comfino') {
                $comfino_is_available = true;

                break;
            }
        }

        if (!$comfino_is_available) {
            exit($this->module->l('This payment method is not available.'));
        }

        $customer = new \Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $shopCart = OrderManager::getShopCart($cart, (int) $cookie->loan_amount);

        $this->module->validateOrder(
            (int) $cart->id,
            (int) Configuration::get('COMFINO_CREATED'),
            (float) ($shopCart->getTotalValue() / 100),
            $this->module->displayName,
            null,
            '',
            (int) $this->context->currency->id,
            false,
            $customer->secure_key
        );

        $order_id = (string) $this->module->currentOrder;
        $addresses = $cart->getAddressCollection();
        $address_parts = explode(' ', $addresses[$cart->id_address_delivery]->address1);
        $building_number = '';

        if (count($address_parts) === 2) {
            $building_number = $address_parts[1];
        }

        $customer_tax_id = trim(str_replace('-', '', $addresses[$cart->id_address_delivery]->vat_number));
        $phone_number = trim($addresses[$cart->id_address_delivery]->phone);

        if (empty($phone_number)) {
            $phone_number = trim($addresses[$cart->id_address_delivery]->phone_mobile);
        }

        $order = (new OrderFactory())->createOrder(
            $order_id,
            $shopCart->getTotalValue(),
            $shopCart->getDeliveryCost(),
            (int) $cookie->loan_term,
            $cookie->loan_type,
            $shopCart->getCartItems(),
            new Customer(
                $addresses[$cart->id_address_delivery]->firstname,
                $addresses[$cart->id_address_delivery]->lastname,
                $customer->email,
                $phone_number,
                Tools::getRemoteAddr(),
                preg_match('/^[A-Z]{0,3}\d{7,}$/', $customer_tax_id) ? $customer_tax_id : null,
                !$customer->is_guest,
                $customer->isLogged(),
                new Address(
                    $address_parts[0],
                    $building_number,
                    null,
                    $addresses[$cart->id_address_delivery]->postcode,
                    $addresses[$cart->id_address_delivery]->city,
                    'PL'
                )
            ),
            Tools::getHttpHost(true) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' .
            "$cart->id&id_module={$this->module->id}&id_order=$order_id&key={$customer->secure_key}",
            $this->context->link->getModuleLink($this->context->controller->module->name, 'notify', [], true),
            SettingsManager::getAllowedProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL, $shopCart)
        );

        try {
            Tools::redirect(ApiClient::getInstance($this->module)->createOrder($order)->applicationUrl);
        } catch (\Throwable $e) {
            $order = new Order($this->module->currentOrder);
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            $order->save();

            ApiClient::processApiError(
                'Order creation error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                $e
            );

            Tools::redirect($this->context->link->getModuleLink(
                $this->module->name,
                'error',
                ['error' => $e->getMessage()],
                true
            ));
        }
    }

    private function redirectWithNotificationsPs16(): void
    {
        $notifications = json_encode(['error' => $this->errors]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['notifications'] = $notifications;
        } else {
            setcookie('notifications', $notifications);
        }

        call_user_func_array(['Tools', 'redirect'], func_get_args());
    }
}
