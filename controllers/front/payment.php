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

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Api/Cart.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ConfigManager.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Order/Customer/Address.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Order/Customer.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Order/LoanParameters.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Order/Order.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/OrderManager.php';
require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

use Comfino\Api;
use Comfino\ErrorLogger;
use Comfino\Order\CustomerInterface;
use Comfino\Order\LoanParameters;
use Comfino\OrderManager;

class ComfinoPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @throws Exception
     */
    public function postProcess()
    {
        Api::init($this->module);
        ErrorLogger::init();

        parent::postProcess();

        if (!($this->module instanceof Comfino)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cart = $this->context->cart;

        if ($cart->id_customer === 0 || $cart->id_address_delivery === 0 || $cart->id_address_invoice === 0
            || !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cookie = Context::getContext()->cookie;

        if (!$cookie->loan_type || !$cookie->loan_term) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $tools = new Comfino\Tools($this->context);

        $billing_address = $cart->getAddressCollection()[$cart->id_address_invoice];
        $delivery_address = $cart->getAddressCollection()[$cart->id_address_delivery];

        if ($billing_address === null) {
            $billing_address = $delivery_address;
        }

        $phone_number = trim(isset($billing_address->phone) ? $billing_address->phone : '');

        if (empty($phone_number)) {
            $phone_number = trim(isset($billing_address->phone_mobile) ? $billing_address->phone_mobile : '');
        }

        if (!empty(trim($delivery_address->phone))) {
            $phone_number = trim($delivery_address->phone);
        }

        if (!empty(trim($delivery_address->phone_mobile))) {
            $phone_number = trim($delivery_address->phone_mobile);
        }

        if (empty($phone_number)) {
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

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $ps_order = new Order($this->module->currentOrder);

        if (\ValidateCore::isLoadedObject($ps_order)) {
            $shop_cart = OrderManager::getShopCartFromOrder($ps_order);
        } else {
            $shop_cart = OrderManager::getShopCart($cart);
        }

        $this->module->validateOrder(
            (int) $cart->id,
            (int) Configuration::get('COMFINO_CREATED'),
            (float) ($shop_cart->getTotalValue() / 100),
            $this->module->displayName,
            null,
            '',
            (int) $this->context->currency->id,
            false,
            $customer->secure_key
        );

        $order_id = (string) $this->module->currentOrder;

        if (!empty(trim(isset($billing_address->firstname) ? $billing_address->firstname : ''))) {
            // Use billing address to get customer names.
            list($first_name, $last_name) = $this->prepareCustomerNames($billing_address);
        } else {
            // Use delivery address to get customer names.
            list($first_name, $last_name) = $this->prepareCustomerNames($delivery_address);
        }

        $billing_address_lines = $billing_address->address1;

        if (!empty($billing_address->address2)) {
            $billing_address_lines .= " $billing_address->address2";
        }

        if (empty($billing_address_lines)) {
            $delivery_address_lines = $delivery_address->address1;

            if (!empty($delivery_address->address2)) {
                $delivery_address_lines .= " {$delivery_address->address2}";
            }

            $street = trim($delivery_address_lines);
        } else {
            $street = trim($billing_address_lines);
        }

        $address_parts = explode(' ', $street);
        $building_number = '';

        if (count($address_parts) > 1) {
            foreach ($address_parts as $idx => $addressPart) {
                if (preg_match('/^\d+[a-zA-Z]?$/', trim($addressPart))) {
                    $street = implode(' ', array_slice($address_parts, 0, $idx));
                    $building_number = trim($addressPart);
                }
            }
        }

        $customer_tax_id = trim(str_replace('-', '', isset($billing_address->vat_number)
            ? $billing_address->vat_number : ''));

        $return_url = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' .
            "$cart->id&id_module={$this->module->id}&id_order=$order_id&key={$customer->secure_key}";
        $notify_url = $this->context->link->getModuleLink($this->context->controller->module->name, 'notify', [], true);

        $order = $this->createOrder(
            $order_id,
            $shop_cart->getTotalValue(),
            $shop_cart->getDeliveryCost(),
            (int) $cookie->loan_term,
            $cookie->loan_type,
            $shop_cart->getCartItems(),
            new Comfino\Order\Customer(
                $first_name,
                $last_name,
                $customer->email,
                $phone_number,
                Tools::getRemoteAddr(),
                preg_match('/^[A-Z]{0,3}\d{7,}$/', $customer_tax_id) ? $customer_tax_id : null,
                !$customer->is_guest,
                $customer->isLogged(),
                new Comfino\Order\Customer\Address(
                    $street,
                    $building_number,
                    null,
                    !empty($delivery_address->postcode),
                    $delivery_address->city,
                    $tools->getCountryIsoCode($delivery_address->id_country)
                )
            ),
            $return_url,
            $notify_url,
            (new Comfino\ConfigManager($this->module))->getAllowedProductTypes('paywall', $shop_cart),
            $shop_cart->getDeliveryNetCost(),
            $shop_cart->getDeliveryTaxRate(),
            $shop_cart->getDeliveryTaxValue()
        );

        $order_response = Api::createOrder($order);

        if (!is_array($order_response) || !isset($order_response['applicationUrl'])) {
            $ps_order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            $ps_order->save();

            ErrorLogger::sendError(
                'Order creation error',
                0,
                'Wrong Comfino API response.',
                Api::getLastResponseCode(),
                $_SERVER['REQUEST_URI'],
                Api::getLastRequestBody(),
                is_array($order_response) ? json_encode($order_response) : Api::getLastResponseBody()
            );

            Tools::redirect($this->context->link->getModuleLink(
                $this->module->name,
                'error',
                [
                    'error' => is_array($order_response) && isset($order_response['errors'])
                        ? implode(',', $order_response['errors'])
                        : 'Order creation error.',
                ],
                true
            ));
        }

        Tools::redirect($order_response['applicationUrl']);
    }

    // FIXME Implement proper logic for PrestaShop 1.6.
    private function redirectWithNotificationsPs16()
    {
        $notifications = json_encode(['error' => $this->errors]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['notifications'] = $notifications;
        } else {
            setcookie('notifications', $notifications);
        }

        call_user_func_array(['Tools', 'redirect'], func_get_args());
    }

    /**
     * @param Address $address
     *
     * @return string[]
     */
    private function prepareCustomerNames(Address $address)
    {
        $first_name = trim(isset($address->firstname) ? $address->firstname : '');
        $last_name = trim(isset($address->lastname) ? $address->lastname : '');

        if (empty($last_name)) {
            $nameParts = explode(' ', $first_name);

            if (count($nameParts) > 1) {
                list($first_name, $last_name) = $nameParts;
            }
        }

        return [$first_name, $last_name];
    }

    /**
     * @param string $orderId
     * @param int $orderTotal
     * @param int $deliveryCost
     * @param int $loanTerm
     * @param string $loanType
     * @param array $cartItems
     * @param CustomerInterface $customer
     * @param string $returnUrl
     * @param string $notificationUrl
     * @param array|null $allowedProductTypes
     * @param int|null $deliveryNetCost
     * @param int|null $deliveryCostTaxRate
     * @param int|null $deliveryCostTaxValue
     * @param string|null $category
     *
     * @return Comfino\Order\Order
     */
    private function createOrder(
        $orderId,
        $orderTotal,
        $deliveryCost,
        $loanTerm,
        $loanType,
        array $cartItems,
        CustomerInterface $customer,
        $returnUrl,
        $notificationUrl,
        $allowedProductTypes = null,
        $deliveryNetCost = null,
        $deliveryCostTaxRate = null,
        $deliveryCostTaxValue = null,
        $category = null
    ) {
        return new Comfino\Order\Order(
            $orderId,
            $returnUrl,
            new LoanParameters(
                $orderTotal,
                $loanTerm,
                $loanType,
                $allowedProductTypes
            ),
            new Comfino\Api\Cart(
                $cartItems,
                $orderTotal,
                $deliveryCost,
                $deliveryNetCost,
                $deliveryCostTaxRate,
                $deliveryCostTaxValue,
                $category
            ),
            $customer,
            $notificationUrl
        );
    }
}
