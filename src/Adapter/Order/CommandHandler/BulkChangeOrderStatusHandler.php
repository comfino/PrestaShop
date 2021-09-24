<?php

namespace Comfino\Adapter\Order\CommandHandler;

use Carrier;
use Configuration;
use Context;
use Order;
use OrderHistory;
use OrderState;
use OrdersList;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\BulkChangeOrderStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\CommandHandler\BulkChangeOrderStatusHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\ChangeOrderStatusException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\ValueObject\OrderId;
use StockAvailable;

class BulkChangeOrderStatusHandler implements BulkChangeOrderStatusHandlerInterface
{
    /**
     * @param BulkChangeOrderStatusCommand $command
     */
    public function handle(BulkChangeOrderStatusCommand $command)
    {
        require_once __DIR__.'/../../../../models/OrdersList.php';

        $orderState = new OrderState($command->getNewOrderStatusId());

        if ($orderState->id !== $command->getNewOrderStatusId()) {
            throw new OrderException(sprintf('Order state with ID "%s" was not found.', $command->getNewOrderStatusId()));
        }

        $ordersWithFailedToUpdateStatus = [];
        $ordersWithFailedToSendEmail = [];
        $ordersWithAssignedStatus = [];

        $comfinoStates = [
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_PAYMENT],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_ACCEPTED],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_PAID]
        ];

        $orderStateName = is_array($orderState->name) ? current($orderState->name) : $orderState->name;

        foreach ($command->getOrderIds() as $orderId) {
            $order = $this->getOrderObject($orderId);
            $currentOrderState = $order->getCurrentOrderState();
            $payments = $order->getOrderPayments();

            if ($currentOrderState !== null && $orderStateName === 'Canceled' && count($payments)) {
                /** @var \OrderPayment $payment */
                $payment = $payments[0];
                $orderCurrentStateName =  is_array($currentOrderState->name) ? current($currentOrderState->name) : $currentOrderState->name;

                if ($payment->payment_method === 'Comfino payments' && ($currentOrderState->paid || in_array($orderCurrentStateName, $comfinoStates, true))) {
                    continue;
                }
            }

            if ($currentOrderState->id === $orderState->id) {
                $ordersWithAssignedStatus[] = $orderId;

                continue;
            }

            $history = new OrderHistory();
            $history->id_order = $order->id;
            $history->id_employee = (int) Context::getContext()->employee->id;

            $useExistingPayment = !$order->hasInvoice();
            $history->changeIdOrderState((int) $orderState->id, $order, $useExistingPayment);

            $carrier = new Carrier($order->id_carrier, (int) $order->getAssociatedLanguage()->getId());
            $templateVars = [];

            if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $order->shipping_number) {
                $templateVars['{followup}'] = str_replace('@', $order->shipping_number, $carrier->url);
            }

            if (!$history->add()) {
                $ordersWithFailedToUpdateStatus[] = $orderId;

                continue;
            }

            if (!$history->sendEmail($order, $templateVars)) {
                $ordersWithFailedToSendEmail[] = $orderId;

                continue;
            }

            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                foreach ($order->getProducts() as $product) {
                    if (StockAvailable::dependsOnStock($product['product_id'])) {
                        StockAvailable::synchronize($product['product_id'], (int) $product['id_shop']);
                    }
                }
            }
        }

        if (!empty($ordersWithFailedToUpdateStatus)
            || !empty($ordersWithFailedToSendEmail)
            || !empty($ordersWithAssignedStatus)
        ) {
            throw new ChangeOrderStatusException($ordersWithFailedToUpdateStatus, $ordersWithFailedToSendEmail, $ordersWithAssignedStatus, 'Failed to update status or sent email when changing order status.');
        }
    }

    /**
     * @param OrderId $orderId
     *
     * @return Order
     */
    private function getOrderObject(OrderId $orderId)
    {
        $order = new Order($orderId->getValue());

        if ($order->id !== $orderId->getValue()) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }
}
