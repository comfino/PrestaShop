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
use Comfino\Shop\Order\Order;
use Comfino\Shop\Order\OrderInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @throws PrestaShopException
     * @throws PrestaShopDatabaseException
     */
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

        // Basic cart validation before proceeding.
        if ($cart->id_customer === 0 || $cart->id_address_delivery === 0 || $cart->id_address_invoice === 0) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $customer = new \Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $cookie = $this->context->cookie;

        // Basic loan parameters validation.
        if (!$cookie->loan_type || !$cookie->loan_term) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        /* Check that this payment option is still available in case the customer changed his address just before
           the end of the checkout process. */
        $comfinoIsAvailable = false;

        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] === $this->module->name) {
                $comfinoIsAvailable = true;

                break;
            }
        }

        if (!isset($this->errors)) {
            $this->errors = [];
        }

        if (!$comfinoIsAvailable) {
            $this->errors = [$this->module->l('This payment method is not available.')];

            if (COMFINO_PS_17) {
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            } else {
                $this->redirectWithNotificationsPs16('index.php?controller=order&step=1');
            }

            return;
        }

        $customer = new \Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');

            return;
        }

        $initLoanAmount = (int) filter_var($cookie->loan_amount, FILTER_VALIDATE_INT);
        $priceModifier = (int) filter_var($cookie->price_modifier, FILTER_VALIDATE_INT);
        $loanType = trim(filter_var($cookie->loan_type, FILTER_SANITIZE_STRING));
        $loanTerm = (int) filter_var($cookie->loan_term, FILTER_VALIDATE_INT);

        $psOrder = new \Order($this->module->currentOrder);

        if (\ValidateCore::isLoadedObject($psOrder)) {
            $shopCart = OrderManager::getShopCartFromOrder($psOrder, $priceModifier, true);
        } else {
            $shopCart = OrderManager::getShopCart($cart, $priceModifier, true);
        }

        $shopCustomer = OrderManager::getShopCustomerFromCart($cart, $customer, $this->context);

        // Use temporary order ID for validation purposes (cart not yet cleared).
        $tempOrderId = 'order_validation_' . $cart->id . '_' . time();
        $returnUrl = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'index.php?' . http_build_query([
            'controller' => 'order-confirmation',
            'id_cart' => $cart->id,
            'id_module' => $this->module->id,
            'id_order' => $tempOrderId,
            'key' => $customer->secure_key,
        ]);

        /* Create Order object with temporary ID for validation before validateOrder() call.
           This allows validation to occur before the cart is cleared by validateOrder(). */
        $order = $this->createOrder($tempOrderId, $loanType, $loanTerm, $returnUrl, $shopCart, $shopCustomer);

        // Perform comprehensive validation before validateOrder() to preserve cart on error.
        $validationErrors = $this->validatePaymentData($order, $cart);

        if (!empty($validationErrors)) {
            DebugLogger::logEvent(
                '[PAYMENT]',
                'Validation failed',
                ['errors' => $validationErrors, 'cart_id' => $cart->id]
            );

            foreach ($validationErrors as $error) {
                $this->errors[] = $error;
            }

            if (COMFINO_PS_17) {
                $this->redirectWithNotifications('index.php?controller=order&step=1');
            } else {
                $this->redirectWithNotificationsPs16('index.php?controller=order&step=1');
            }

            return;
        }

        DebugLogger::logEvent(
            '[PAYMENT]',
            'Validation passed - proceeding with order creation',
            [
                '$initLoanAmount' => $initLoanAmount,
                '$priceModifier' => $priceModifier,
                '$cartTotalValue' => $shopCart->getTotalValue(),
                '$loanAmount' => $order->getCart()->getTotalAmount(),
                '$loanType' => (string) $order->getLoanParameters()->getType(),
                '$loanTerm' => $order->getLoanParameters()->getTerm(),
                '$shopCart' => $shopCart->getAsArray(),
            ]
        );

        // Validation passed - create the PrestaShop order (this clears the cart).
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
        $returnUrl = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'index.php?' . http_build_query([
            'controller' => 'order-confirmation',
            'id_cart' => $cart->id,
            'id_module' => $this->module->id,
            'id_order' => $orderId,
            'key' => $customer->secure_key,
        ]);

        // Create Order object with real order ID for Comfino API.
        $order = $this->createOrder($orderId, $loanType, $loanTerm, $returnUrl, $shopCart, $shopCustomer);

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

    /**
     * Validates payment data from Order object before processing.
     *
     * @return string[] Array of error messages, empty if validation passes.
     */
    private function validatePaymentData(OrderInterface $order, Cart $cart): array
    {
        $errors = [];

        // 1. Validate customer e-mail.
        $customerEmail = $order->getCustomer()->getEmail();

        if (empty($customerEmail) || !Validate::isEmail($customerEmail)) {
            $errors[] = $this->module->l('Invalid customer e-mail address. Please check your account contact data.');
        }

        // 2. Validate phone number.
        $phoneNumber = $order->getCustomer()->getPhoneNumber();

        if (empty($phoneNumber)) {
            $errors[] = $this->module->l(
                'Phone number is required. Please add a phone number to your billing or delivery address.'
            );
        }

        // 3. Validate customer names.
        if (empty(trim($order->getCustomer()->getFirstName()))) {
            $errors[] = $this->module->l('First name is required.');
        }

        if (empty(trim($order->getCustomer()->getLastName()))) {
            $errors[] = $this->module->l('Last name is required.');
        }

        // 4. Validate customer address.
        $address = $order->getCustomer()->getAddress();

        if ($address === null) {
            $errors[] = $this->module->l('Delivery address is required.');
        } else {
            if (empty(trim($address->getCity()))) {
                $errors[] = $this->module->l('City/Town is required.');
            }

            if (empty(trim($address->getPostalCode()))) {
                $errors[] = $this->module->l('Postal code is required.');
            }
        }

        // 5. Validate cart data.
        $cartItems = $order->getCart()->getItems();

        if (empty($cartItems)) {
            $errors[] = $this->module->l('Cart is empty. Please add products to your cart.');
        }

        // 6. Validate order amount.
        if ($order->getCart()->getTotalAmount() <= 0) {
            $errors[] = $this->module->l('Cart total amount must be greater than zero.');
        }

        // 7. Validate payment availability.
        if (!Comfino\Main::paymentIsAvailable($this->module, $cart)) {
            $errors[] = $this->module->l(
                'Comfino payment is not available for this cart. Please check cart amount and product types.'
            );
        }

        if (!empty($errors)) {
            // Do not call validation at Comfino API side if any errors detected locally.
            return $errors;
        }

        // Call Comfino API validation as a second step of order validation if no errors detected locally.
        $validationResult = ApiClient::getInstance()->validateOrder($order);

        if (!$validationResult->success) {
            $errors = array_values($validationResult->errors);
        }

        return $errors;
    }

    private function redirectWithNotificationsPs16(string $url): void
    {
        $notifications = json_encode(['errors' => $this->errors]);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['comfino_notifications'] = $notifications;

        Tools::redirect($url);
    }

    private function createOrder(
        string $orderId,
        string $loanType,
        int $loanTerm,
        string $returnUrl,
        Comfino\Common\Shop\Cart $shopCart,
        Comfino\Shop\Order\Customer $shopCustomer
    ): Order
    {
        return (new OrderFactory())->createOrder(
            $orderId,
            $shopCart->getTotalValue(),
            $shopCart->getDeliveryCost(),
            $loanTerm,
            new LoanTypeEnum($loanType, false),
            $shopCart->getCartItems(),
            $shopCustomer,
            $returnUrl,
            ApiService::getEndpointUrl('transactionStatus'),
            SettingsManager::getAllowedProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL, $shopCart),
            $shopCart->getDeliveryNetCost(),
            $shopCart->getDeliveryTaxRate(),
            $shopCart->getDeliveryTaxValue()
        );
    }
}
