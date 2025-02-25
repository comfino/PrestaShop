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
use Comfino\Api\ApiService;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\Factory\OrderFactory;
use Comfino\Configuration\SettingsManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\Shop\Order\Customer;
use Comfino\Shop\Order\Customer\Address;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoPaymentModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        ErrorLogger::init();

        parent::postProcess();

        if (!($this->module instanceof Comfino) || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cart = $this->context->cart;

        DebugLogger::logEvent('[PAYMENT GATEWAY]', 'postProcess', ['cart_id' => $cart->id]);

        if ($cart->id_customer === 0 || $cart->id_address_delivery === 0 || $cart->id_address_invoice === 0) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cookie = $this->context->cookie;

        if (!$cookie->loan_type || !$cookie->loan_term) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $tools = new Comfino\Tools($this->context);

        $billingAddress = $cart->getAddressCollection()[$cart->id_address_invoice];
        $deliveryAddress = $cart->getAddressCollection()[$cart->id_address_delivery];

        if ($billingAddress === null) {
            $billingAddress = $deliveryAddress;
        }

        $phoneNumber = trim($billingAddress->phone ?? '');

        if (empty($phoneNumber)) {
            $phoneNumber = trim($billingAddress->phone_mobile ?? '');
        }

        if (!empty($deliveryAddress->phone)) {
            $phoneNumber = trim($deliveryAddress->phone);
        }

        if (!empty($deliveryAddress->phone_mobile)) {
            $phoneNumber = trim($deliveryAddress->phone_mobile);
        }

        if (empty($phoneNumber)) {
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
        $comfinoIsAvailable = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === 'comfino') {
                $comfinoIsAvailable = true;

                break;
            }
        }

        if (!$comfinoIsAvailable) {
            exit($this->module->l('This payment method is not available.'));
        }

        $customer = new \Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $shopCart = OrderManager::getShopCart($cart, (int) $cookie->loan_amount, true);

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

        $orderId = (string) $this->module->currentOrder;

        if (!empty(trim($billingAddress->firstname ?? ''))) {
            // Use billing address to get customer names.
            [$firstName, $lastName] = $this->prepareCustomerNames($billingAddress);
        } else {
            // Use delivery address to get customer names.
            [$firstName, $lastName] = $this->prepareCustomerNames($deliveryAddress);
        }

        $billingAddressLines = $billingAddress->address1;

        if (!empty($billingAddress->address2)) {
            $billingAddressLines .= " $billingAddress->address2";
        }

        if (empty($billingAddressLines)) {
            $deliveryAddressLines = $deliveryAddress->address1;

            if (!empty($deliveryAddress->address2)) {
                $deliveryAddressLines .= " {$deliveryAddress->address2}";
            }

            $street = trim($deliveryAddressLines);
        } else {
            $street = trim($billingAddressLines);
        }

        $addressParts = explode(' ', $street);
        $buildingNumber = '';

        if (count($addressParts) > 1) {
            foreach ($addressParts as $idx => $addressPart) {
                if (preg_match('/^\d+[a-zA-Z]?$/', trim($addressPart))) {
                    $street = implode(' ', array_slice($addressParts, 0, $idx));
                    $buildingNumber = trim($addressPart);
                }
            }
        }

        $customerTaxId = trim(str_replace('-', '', $billingAddress->vat_number ?? ''));

        $returnUrl = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'index.php?controller=order-confirmation&id_cart=' .
            "$cart->id&id_module={$this->module->id}&id_order=$orderId&key={$customer->secure_key}";

        $order = (new OrderFactory())->createOrder(
            $orderId,
            $shopCart->getTotalValue(),
            $shopCart->getDeliveryCost(),
            (int) $cookie->loan_term,
            new LoanTypeEnum($cookie->loan_type),
            $shopCart->getCartItems(),
            new Customer(
                $firstName,
                $lastName,
                $customer->email,
                $phoneNumber,
                Tools::getRemoteAddr(),
                preg_match('/^[A-Z]{0,3}\d{7,}$/', str_replace('-', '', $customerTaxId)) ? $customerTaxId : null,
                !$customer->is_guest,
                $customer->isLogged(),
                new Address(
                    $street,
                    $buildingNumber,
                    null,
                    !empty($deliveryAddress->postcode),
                    $deliveryAddress->city,
                    $tools->getCountryIsoCode($deliveryAddress->id_country)
                )
            ),
            $returnUrl,
            ApiService::getEndpointUrl('transactionStatus'),
            SettingsManager::getAllowedProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL, $shopCart),
            $shopCart->getDeliveryNetCost(),
            $shopCart->getDeliveryTaxRate(),
            $shopCart->getDeliveryTaxValue()
        );

        DebugLogger::logEvent(
            '[PAYMENT]',
            'ComfinoPaymentModuleFrontController',
            [
                '$loanAmount' => $order->getCart()->getTotalAmount(),
                '$loanType' => (string) $order->getLoanParameters()->getType(),
                '$loanTerm' => $order->getLoanParameters()->getTerm(),
                '$shopCart' => $shopCart->getAsArray(),
            ]
        );

        try {
            Tools::redirect(ApiClient::getInstance()->createOrder($order)->applicationUrl);
        } catch (Throwable $e) {
            $psOrder = new Order($this->module->currentOrder);
            $psOrder->setCurrentState((int) Configuration::get('PS_OS_ERROR'));
            $psOrder->save();

            ApiClient::processApiError(
                'Order creation error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)',
                $e
            );

            Tools::redirect(ApiService::getControllerUrl('error', ['error' => $e->getMessage()]));
        } finally {
            if (($apiRequest = ApiClient::getInstance()->getRequest()) !== null) {
                DebugLogger::logEvent(
                    '[CREATE_ORDER_API_REQUEST]',
                    'createOrder',
                    ['$request' => $apiRequest->getRequestBody()]
                );
            }
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

    private function prepareCustomerNames(\Address $address): array
    {
        $firstName = trim($address->firstname ?? '');
        $lastName = trim($address->lastname ?? '');

        if (empty($lastName)) {
            $nameParts = explode(' ', $firstName);

            if (count($nameParts) > 1) {
                [$firstName, $lastName] = $nameParts;
            }
        }

        return [$firstName, $lastName];
    }
}
