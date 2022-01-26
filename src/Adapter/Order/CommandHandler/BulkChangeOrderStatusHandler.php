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
        require_once _PS_MODULE_DIR_.'comfino/models/OrdersList.php';

        $orderState = new OrderState($command->getNewOrderStatusId());

        if ($orderState->id !== $command->getNewOrderStatusId()) {
            throw new OrderException(
                sprintf('Order state with ID "%s" was not found.', $command->getNewOrderStatusId())
            );
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
                $orderCurrentStateName = is_array($currentOrderState->name)
                    ? current($currentOrderState->name)
                    : $currentOrderState->name;

                if (stripos($payment->payment_method, 'comfino') !== false &&
                    ($currentOrderState->paid || in_array($orderCurrentStateName, $comfinoStates, true))
                ) {
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
            throw new ChangeOrderStatusException(
                $ordersWithFailedToUpdateStatus,
                $ordersWithFailedToSendEmail,
                $ordersWithAssignedStatus,
                'Failed to update status or sent email when changing order status.'
            );
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
