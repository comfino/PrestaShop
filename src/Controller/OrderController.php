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

namespace Comfino\Controller;

use Exception;
use PrestaShop\PrestaShop\Core\Data\AbstractTypedCollection;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Exception\InvalidCartRuleDiscountValueException;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\CannotEditDeliveredOrderProductException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\CannotFindProductInOrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\ChangeOrderStatusException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\DuplicateProductInOrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\DuplicateProductInOrderInvoiceException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidAmountException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidCancelProductException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidOrderStateException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidProductQuantityException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\NegativePaymentAmountException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderEmailSendException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\Invoice\Exception\InvoiceException;
use PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing;
use PrestaShop\PrestaShop\Core\Domain\Order\QueryResult\OrderForViewing;
use PrestaShop\PrestaShop\Core\Domain\Order\ValueObject\OrderId;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductOutOfStockException;
use PrestaShop\PrestaShop\Core\Domain\ValueObject\QuerySorting;
use PrestaShop\PrestaShop\Core\Order\OrderSiblingProviderInterface;
use PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButtonsCollection;
use PrestaShopBundle\Exception\InvalidModuleException;
use PrestaShopBundle\Form\Admin\Sell\Customer\PrivateNoteType;
use PrestaShopBundle\Form\Admin\Sell\Order\AddOrderCartRuleType;
use PrestaShopBundle\Form\Admin\Sell\Order\AddProductRowType;
use PrestaShopBundle\Form\Admin\Sell\Order\ChangeOrderAddressType;
use PrestaShopBundle\Form\Admin\Sell\Order\ChangeOrderCurrencyType;
use PrestaShopBundle\Form\Admin\Sell\Order\EditProductRowType;
use PrestaShopBundle\Form\Admin\Sell\Order\InternalNoteType;
use PrestaShopBundle\Form\Admin\Sell\Order\OrderMessageType;
use PrestaShopBundle\Form\Admin\Sell\Order\OrderPaymentType;
use PrestaShopBundle\Form\Admin\Sell\Order\UpdateOrderShippingType;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Comfino\Form\Admin\Sell\Order\UpdateOrderStatusType;

class OrderController extends \PrestaShopBundle\Controller\Admin\Sell\Order\OrderController
{
    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     *
     * @param int $orderId
     * @param Request $request
     *
     * @return Response
     */
    public function viewAction(int $orderId, Request $request): Response
    {
        try {
            /** @var OrderForViewing $orderForViewing */
            $orderForViewing = $this->getQueryBus()->handle(new GetOrderForViewing($orderId, QuerySorting::DESC));
        } catch (OrderException $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessages($e)));

            return $this->redirectToRoute('admin_orders_index');
        }

        $formFactory = $this->get('form.factory');
        $updateOrderStatusForm = $formFactory->createNamed(
            'update_order_status',
            UpdateOrderStatusType::class,
            [
                'new_order_status_id' => $orderForViewing->getHistory()->getCurrentOrderStatusId(),
                'payment_method' => !empty($payment = $orderForViewing->getPayments()->getPayments())
                    ? $payment[0]->getPaymentMethod()
                    : '',
            ]
        );
        $updateOrderStatusActionBarForm = $formFactory->createNamed(
            'update_order_status_action_bar',
            UpdateOrderStatusType::class,
            [
                'new_order_status_id' => $orderForViewing->getHistory()->getCurrentOrderStatusId(),
                'payment_method' => !empty($payment = $orderForViewing->getPayments()->getPayments())
                    ? $payment[0]->getPaymentMethod()
                    : '',
            ]
        );

        $addOrderCartRuleForm = $this->createForm(
            AddOrderCartRuleType::class,
            [],
            ['order_id' => $orderId]
        );
        $addOrderPaymentForm = $this->createForm(
            OrderPaymentType::class,
            ['id_currency' => $orderForViewing->getCurrencyId()],
            ['id_order' => $orderId]
        );

        $orderMessageForm = $this->createForm(
            OrderMessageType::class,
            [],
            ['action' => $this->generateUrl('admin_orders_send_message', ['orderId' => $orderId])]
        );
        $orderMessageForm->handleRequest($request);

        $changeOrderCurrencyForm = $this->createForm(
            ChangeOrderCurrencyType::class,
            [],
            ['current_currency_id' => $orderForViewing->getCurrencyId()]
        );

        $changeOrderAddressForm = null;
        $privateNoteForm = null;

        if (null !== $orderForViewing->getCustomer()) {
            $changeOrderAddressForm = $this->createForm(
                ChangeOrderAddressType::class,
                [],
                ['customer_id' => $orderForViewing->getCustomer()->getId()]
            );

            $privateNoteForm = $this->createForm(
                PrivateNoteType::class,
                ['note' => $orderForViewing->getCustomer()->getPrivateNote()]
            );
        }

        $updateOrderShippingForm = $this->createForm(
            UpdateOrderShippingType::class,
            ['new_carrier_id' => $orderForViewing->getCarrierId()],
            ['order_id' => $orderId]
        );

        $currencyDataProvider = $this->container->get('prestashop.adapter.data_provider.currency');
        $orderCurrency = $currencyDataProvider->getCurrencyById($orderForViewing->getCurrencyId());

        $addProductRowForm = $this->createForm(
            AddProductRowType::class,
            [],
            [
                'order_id' => $orderId,
                'currency_id' => $orderForViewing->getCurrencyId(),
                'symbol' => $orderCurrency->symbol,
            ]
        );
        $editProductRowForm = $this->createForm(
            EditProductRowType::class,
            [],
            [
                'order_id' => $orderId,
                'symbol' => $orderCurrency->symbol,
            ]
        );

        if (method_exists($orderForViewing, 'getNote')) {
            $internalNoteForm = $this->createForm(
                InternalNoteType::class,
                ['note' => $orderForViewing->getNote()]
            );
        } else {
            $internalNoteForm = null;
        }

        $formBuilder = $this->get('prestashop.core.form.identifiable_object.builder.cancel_product_form_builder');
        $backOfficeOrderButtons = new ActionsBarButtonsCollection();

        try {
            $this->dispatchHook(
                'actionGetAdminOrderButtons',
                [
                    'controller' => $this,
                    'id_order' => $orderId,
                    'actions_bar_buttons_collection' => $backOfficeOrderButtons,
                ]
            );

            $cancelProductForm = $formBuilder->getFormFor($orderId);
        } catch (Exception $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessages($e)));

            return $this->redirectToRoute('admin_orders_index');
        }

        $this->handleOutOfStockProduct($orderForViewing);

        $merchandiseReturnEnabled = (bool) $this->configuration->get('PS_ORDER_RETURN');

        /** @var OrderSiblingProviderInterface $orderSiblingProvider */
        $orderSiblingProvider = $this->get('prestashop.adapter.order.order_sibling_provider');

        $paginationNum = (int) $this->configuration->get(
            'PS_ORDER_PRODUCTS_NB_PER_PAGE',
            self::DEFAULT_PRODUCTS_NUMBER
        );
        $paginationNumOptions = self::PRODUCTS_PAGINATION_OPTIONS;
        if (!in_array($paginationNum, $paginationNumOptions)) {
            $paginationNumOptions[] = $paginationNum;
        }
        sort($paginationNumOptions);

        return $this->render('@PrestaShop/Admin/Sell/Order/Order/view.html.twig', [
            'showContentHeader' => true,
            'enableSidebar' => true,
            'orderCurrency' => $orderCurrency,
            'meta_title' => $this->trans('Orders', 'Admin.Orderscustomers.Feature'),
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
            'orderForViewing' => $orderForViewing,
            'addOrderCartRuleForm' => $addOrderCartRuleForm->createView(),
            'updateOrderStatusForm' => $updateOrderStatusForm->createView(),
            'updateOrderStatusActionBarForm' => $updateOrderStatusActionBarForm->createView(),
            'addOrderPaymentForm' => $addOrderPaymentForm->createView(),
            'changeOrderCurrencyForm' => $changeOrderCurrencyForm->createView(),
            'privateNoteForm' => $privateNoteForm ? $privateNoteForm->createView() : null,
            'updateOrderShippingForm' => $updateOrderShippingForm->createView(),
            'cancelProductForm' => $cancelProductForm->createView(),
            'invoiceManagementIsEnabled' => $orderForViewing->isInvoiceManagementIsEnabled(),
            'changeOrderAddressForm' => $changeOrderAddressForm ? $changeOrderAddressForm->createView() : null,
            'orderMessageForm' => $orderMessageForm->createView(),
            'addProductRowForm' => $addProductRowForm->createView(),
            'editProductRowForm' => $editProductRowForm->createView(),
            'backOfficeOrderButtons' => $backOfficeOrderButtons,
            'merchandiseReturnEnabled' => $merchandiseReturnEnabled,
            'priceSpecification' => $this->getContextLocale()->getPriceSpecification($orderCurrency->iso_code)
                ->toArray(),
            'previousOrderId' => $orderSiblingProvider->getPreviousOrderId($orderId),
            'nextOrderId' => $orderSiblingProvider->getNextOrderId($orderId),
            'paginationNum' => $paginationNum,
            'paginationNumOptions' => $paginationNumOptions,
            'isAvailableQuantityDisplayed' => $this->configuration->getBoolean('PS_STOCK_MANAGEMENT'),
            'internalNoteForm' => $internalNoteForm ? $internalNoteForm->createView() : null,
        ]);
    }

    /**
     * @param int $orderId
     * @param Request $request
     *
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", redirectRoute="admin_orders_index")
     *
     * @return RedirectResponse
     */
    public function updateStatusAction(int $orderId, Request $request): RedirectResponse
    {
        $formFactory = $this->get('form.factory');

        $form = $formFactory->createNamed(
            'update_order_status',
            UpdateOrderStatusType::class
        );
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            // Check if the form is submit from the action bar
            $form = $formFactory->createNamed(
                'update_order_status_action_bar',
                UpdateOrderStatusType::class
            );
            $form->handleRequest($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleOrderStatusUpdate($orderId, (int) $form->getData()['new_order_status_id']);
        }

        return $this->redirectToRoute('admin_orders_view', [
            'orderId' => $orderId,
        ]);
    }

    /**
     * Initializes order status update
     *
     * @param int $orderId
     * @param int $orderStatusId
     */
    private function handleOrderStatusUpdate(int $orderId, int $orderStatusId): void
    {
        try {
            $this->getCommandBus()->handle(
                new UpdateOrderStatusCommand(
                    $orderId,
                    $orderStatusId
                )
            );
            $this->addFlash('success', $this->trans('Successful update.', 'Admin.Notifications.Success'));
        } catch (ChangeOrderStatusException $e) {
            $this->handleChangeOrderStatusException($e);
        } catch (Exception $e) {
            $this->addFlash('error', $this->getErrorMessageForException($e, $this->getErrorMessages($e)));
        }
    }

    /**
     * @param Exception $e
     *
     * @return array
     */
    private function getErrorMessages(Exception $e)
    {
        $refundableQuantity = 0;
        if ($e instanceof InvalidCancelProductException) {
            $refundableQuantity = $e->getRefundableQuantity();
        }
        $orderInvoiceNumber = '#unknown';
        if ($e instanceof DuplicateProductInOrderInvoiceException) {
            $orderInvoiceNumber = $e->getOrderInvoiceNumber();
        }

        return [
            CannotEditDeliveredOrderProductException::class =>
                $this->trans(
                    'You cannot edit the cart once the order delivered.',
                    'Admin.Orderscustomers.Notification'
                ),
            OrderNotFoundException::class => $e instanceof OrderNotFoundException ?
                $this->trans(
                    'Order #%d cannot be loaded.',
                    'Admin.Orderscustomers.Notification',
                    ['#%d' => $e->getOrderId()->getValue()]
                ) : '',
            OrderEmailSendException::class => $this->trans(
                'An error occurred while sending the e-mail to the customer.',
                'Admin.Orderscustomers.Notification'
            ),
            OrderException::class => $this->trans(
                $e->getMessage(),
                'Admin.Orderscustomers.Notification'
            ),
            InvoiceException::class => $this->trans(
                $e->getMessage(),
                'Admin.Orderscustomers.Notification'
            ),
            InvalidAmountException::class => $this->trans(
                'Only numbers and decimal points (".") are allowed in the amount fields, e.g. 10.50 or 1050.',
                'Admin.Orderscustomers.Notification'
            ),
            InvalidCartRuleDiscountValueException::class => [
                InvalidCartRuleDiscountValueException::INVALID_MIN_PERCENT => $this->trans(
                    'Percent value must be greater than 0.',
                    'Admin.Orderscustomers.Notification'
                ),
                InvalidCartRuleDiscountValueException::INVALID_MAX_PERCENT => $this->trans(
                    'Percent value cannot exceed 100.',
                    'Admin.Orderscustomers.Notification'
                ),
                InvalidCartRuleDiscountValueException::INVALID_MIN_AMOUNT => $this->trans(
                    'Amount value must be greater than 0.',
                    'Admin.Orderscustomers.Notification'
                ),
                InvalidCartRuleDiscountValueException::INVALID_MAX_AMOUNT => $this->trans(
                    'Discount value cannot exceed the total price of this order.',
                    'Admin.Orderscustomers.Notification'
                ),
                InvalidCartRuleDiscountValueException::INVALID_FREE_SHIPPING => $this->trans(
                    'Shipping discount value cannot exceed the total price of this order.',
                    'Admin.Orderscustomers.Notification'
                ),
            ],
            InvalidCancelProductException::class => [
                InvalidCancelProductException::INVALID_QUANTITY => $this->trans(
                    'Positive product quantity is required.',
                    'Admin.Notifications.Error'
                ),
                InvalidCancelProductException::QUANTITY_TOO_HIGH => $this->trans(
                    'Please enter a maximum quantity of [1].',
                    'Admin.Orderscustomers.Notification',
                    ['[1]' => $refundableQuantity]
                ),
                InvalidCancelProductException::NO_REFUNDS => $this->trans(
                    'Please select at least one product.',
                    'Admin.Orderscustomers.Notification'
                ),
                InvalidCancelProductException::INVALID_AMOUNT => $this->trans(
                    'Please enter a positive amount.',
                    'Admin.Orderscustomers.Notification'
                ),
                InvalidCancelProductException::NO_GENERATION => $this->trans(
                    'Please generate at least one credit slip or voucher.',
                    'Admin.Orderscustomers.Notification'
                ),
            ],
            InvalidModuleException::class => $this->trans(
                'You must choose a payment module to create the order.',
                'Admin.Orderscustomers.Notification'
            ),
            ProductOutOfStockException::class => $this->trans(
                'There are not enough products in stock.',
                'Admin.Catalog.Notification'
            ),
            NegativePaymentAmountException::class => $this->trans(
                'Invalid value: the payment must be a positive amount.',
                'Admin.Notifications.Error'
            ),
            InvalidOrderStateException::class => [
                InvalidOrderStateException::ALREADY_PAID => $this->trans(
                    'Invalid action: this order has already been paid.',
                    'Admin.Notifications.Error'
                ),
                InvalidOrderStateException::DELIVERY_NOT_FOUND => $this->trans(
                    'Invalid action: this order has not been delivered.',
                    'Admin.Notifications.Error'
                ),
                InvalidOrderStateException::UNEXPECTED_DELIVERY => $this->trans(
                    'Invalid action: this order has already been delivered.',
                    'Admin.Notifications.Error'
                ),
                InvalidOrderStateException::NOT_PAID => $this->trans(
                    'Invalid action: this order has not been paid.',
                    'Admin.Notifications.Error'
                ),
                InvalidOrderStateException::INVALID_ID => $this->trans(
                    'You must choose an order status to create the order.',
                    'Admin.Orderscustomers.Notification'
                ),
            ],

            OrderConstraintException::class => [
                OrderConstraintException::INVALID_CUSTOMER_MESSAGE => $this->trans(
                    'The order message given is invalid.',
                    'Admin.Orderscustomers.Notification'
                ),
            ],
            InvalidProductQuantityException::class => $this->trans(
                'Positive product quantity is required.',
                'Admin.Notifications.Error'
            ),
            DuplicateProductInOrderException::class => $this->trans(
                'This product is already in your order, please edit the quantity instead.',
                'Admin.Notifications.Error'
            ),
            DuplicateProductInOrderInvoiceException::class => $this->trans(
                'This product is already in the invoice [1], please edit the quantity instead.',
                'Admin.Notifications.Error',
                ['[1]' => $orderInvoiceNumber]
            ),
            CannotFindProductInOrderException::class => $this->trans(
                'You cannot edit the price of a product that no longer exists in your catalog.',
                'Admin.Notifications.Error'
            ),
        ];
    }

    /**
     * @param OrderForViewing $orderForViewing
     */
    private function handleOutOfStockProduct(OrderForViewing $orderForViewing)
    {
        $isStockManagementEnabled = $this->configuration->getBoolean('PS_STOCK_MANAGEMENT');
        if (!$isStockManagementEnabled || $orderForViewing->isDelivered() || $orderForViewing->isShipped()) {
            return;
        }

        foreach ($orderForViewing->getProducts()->getProducts() as $product) {
            if ($product->getAvailableQuantity() <= 0) {
                $this->addFlash(
                    'warning',
                    $this->trans('This product is out of stock:', 'Admin.Orderscustomers.Notification') .
                    ' ' . $product->getName()
                );
            }
        }
    }

    /**
     * @param ChangeOrderStatusException $e
     */
    private function handleChangeOrderStatusException(ChangeOrderStatusException $e)
    {
        $orderIds = array_merge(
            $e->getOrdersWithFailedToUpdateStatus(),
            $e->getOrdersWithFailedToSendEmail()
        );

        /** @var OrderId $orderId */
        foreach ($orderIds as $orderId) {
            $this->addFlash(
                'error',
                $this->trans(
                    'An error occurred while changing the status for order #%d, or we were unable to send ' .
                    'an email to the customer.',
                    'Admin.Orderscustomers.Notification',
                    ['#%d' => $orderId->getValue()]
                )
            );
        }

        foreach ($e->getOrdersWithAssignedStatus() as $orderId) {
            $this->addFlash(
                'error',
                $this->trans(
                    'Order #%d has already been assigned this status.',
                    'Admin.Orderscustomers.Notification',
                    ['#%d' => $orderId->getValue()]
                )
            );
        }
    }
}
