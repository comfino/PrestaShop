<?php

namespace Comfino\Adapter\Order\CommandHandler;

use Carrier;
use Configuration;
use Context;
use OrderHistory;
use OrderState;
use OrdersList;
use PrestaShop\PrestaShop\Adapter\Order\AbstractOrderHandler;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\CommandHandler\UpdateOrderStatusHandlerInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\ChangeOrderStatusException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use StockAvailable;

/**
 * @internal
 */
final class UpdateOrderStatusHandler extends AbstractOrderHandler implements UpdateOrderStatusHandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(UpdateOrderStatusCommand $command)
    {
        require_once _PS_MODULE_DIR_.'comfino/models/OrdersList.php';

        $order = $this->getOrder($command->getOrderId());
        $orderState = $this->getOrderStateObject($command->getNewOrderStatusId());

        $currentOrderState = $order->getCurrentOrderState();

        if ($currentOrderState->id == $orderState->id) {
            throw new OrderException('The order has already been assigned this status.');
        }

        $orderStateName = is_array($orderState->name) ? current($orderState->name) : $orderState->name;
        $payments = $order->getOrderPayments();

        if ($currentOrderState !== null && $orderStateName === 'Canceled' && count($payments)) {
            /** @var \OrderPayment $payment */
            $payment = $payments[0];
            $orderCurrentStateName =  is_array($currentOrderState->name) ? current($currentOrderState->name) : $currentOrderState->name;
            $comfinoStates = [
                OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_PAYMENT],
                OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_ACCEPTED],
                OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_PAID]
            ];

            if ($payment->payment_method === 'Comfino payments' && ($currentOrderState->paid || in_array($orderCurrentStateName, $comfinoStates, true))) {
                throw new OrderException('Cancellation of accepted order paid via Comfino service is not allowed.');
            }
        }

        // Create new OrderHistory
        $history = new OrderHistory();
        $history->id_order = $order->id;
        $history->id_employee = (int) Context::getContext()->employee->id;

        $useExistingPayments = false;
        if (!$order->hasInvoice()) {
            $useExistingPayments = true;
        }

        $history->changeIdOrderState((int) $orderState->id, $order, $useExistingPayments);

        $carrier = new Carrier($order->id_carrier, (int) $order->getAssociatedLanguage()->getId());
        $templateVars = [];

        if ($history->id_order_state == Configuration::get('PS_OS_SHIPPING') && $order->shipping_number) {
            $templateVars = [
                '{followup}' => str_replace('@', $order->shipping_number, $carrier->url),
            ];
        }

        // Save all changes
        $historyAdded = $history->addWithemail(true, $templateVars);

        if ($historyAdded) {
            // synchronizes quantities if needed..
            if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                foreach ($order->getProducts() as $product) {
                    if (StockAvailable::dependsOnStock($product['product_id'])) {
                        StockAvailable::synchronize($product['product_id'], (int) $product['id_shop']);
                    }
                }
            }

            return;
        }

        throw new ChangeOrderStatusException([], [$command->getOrderId()], [], 'Failed to update status or sent email when changing order status.');
    }

    /**
     * @param int $orderStatusId
     *
     * @return OrderState
     */
    private function getOrderStateObject($orderStatusId)
    {
        $orderState = new OrderState($orderStatusId);

        if ($orderState->id !== $orderStatusId) {
            throw new OrderException(sprintf('Order status with id "%s" was not found.', $orderStatusId));
        }

        return $orderState;
    }
}
