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

class AdminOrdersController extends AdminOrdersControllerCore
{
    private $orderStates;
    private $comfinoConfirmStates;
    private $comfinoStates;

    public function __construct()
    {
        parent::__construct();

        require_once _PS_MODULE_DIR_.'comfino/src/Api.php';
        require_once _PS_MODULE_DIR_.'comfino/models/OrdersList.php';

        $this->orderStates = OrderState::getOrderStates((int) $this->context->language->id);

        $orderStatesMap = array_combine(
            array_map(
                static function ($itemValue) {
                    return $itemValue['id_order_state'];
                },
                $this->orderStates
            ),
            array_keys($this->orderStates)
        );

        $this->comfinoConfirmStates = [
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_PAYMENT],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_ACCEPTED],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_PAID]
        ];
        $this->comfinoStates = [
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_CREATED],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_FILLING],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_CONFIRMATION],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_WAITING_FOR_PAYMENT],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_ACCEPTED],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_PAID],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_REJECTED],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_CANCELLED_BY_SHOP],
            OrdersList::ADD_ORDER_STATUSES[OrdersList::COMFINO_CANCELLED]
        ];

        if (Tools::isSubmit('id_order') && Tools::getValue('id_order') > 0) {
            $order = new Order(Tools::getValue('id_order'));

            if (!Validate::isLoadedObject($order)) {
                $this->errors[] = Tools::displayError('The order cannot be found within your database.');
            }

            ShopUrl::cacheMainDomainForShop((int) $order->id_shop);
        }

        $paymentMethod = isset($order) ? $order->payment : '';
        $orderStateId = isset($order) ? $order->current_state : '';

        foreach ($this->statuses_array as $statusId => $statusName) {
            if (in_array($statusName, $this->comfinoStates, true) || (
                    stripos($paymentMethod, 'comfino') !== false &&
                    $statusId == Configuration::get('PS_OS_CANCELED') &&
                    !empty($orderStateId) && (
                        $this->orderStates[$orderStatesMap[$orderStateId]]['paid'] == 1 ||
                        in_array($statusName, $this->comfinoConfirmStates, true)
                    )
                )
            ) {
                unset($this->statuses_array[$statusId], $this->orderStates[$orderStatesMap[$statusId]]);
            }
        }

        $this->fields_list['osname']['list'] = $this->statuses_array;
    }

    public function getTemplateViewVars()
    {
        if (isset($this->tpl_view_vars['states'])) {
            $this->tpl_view_vars['states'] = $this->orderStates;
        }

        return parent::getTemplateViewVars();
    }

    public function processBulkUpdateOrderStatus()
    {
        if (Tools::isSubmit('submitUpdateOrderStatus') && ($id_order_state = (int) Tools::getValue('id_order_state'))) {
            if ($this->tabAccess['edit'] !== '1') {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            } else {
                $order_state = new OrderState($id_order_state);

                if (!Validate::isLoadedObject($order_state)) {
                    $this->errors[] = sprintf(Tools::displayError("Order status #%d can't be loaded"), $id_order_state);
                } else {
                    foreach (Tools::getValue('orderBox') as $idx => $id_order) {
                        $order = new Order((int) $id_order);

                        if (!Validate::isLoadedObject($order)) {
                            $this->errors[] = sprintf(Tools::displayError("Order #%d can't be loaded"), $id_order);
                        } else {
                            $current_order_state = $order->getCurrentOrderState();

                            if ($current_order_state->id == $order_state->id) {
                                $this->errors[] = $this->displayWarning(
                                    sprintf('Order #%d has already been assigned this status.', $id_order)
                                );
                            } else {
                                if ($current_order_state->id != $order_state->id) {
                                    if ($current_order_state !== null && $current_order_state->id != $order_state->id &&
                                        $order_state->id == Configuration::get('PS_OS_CANCELED') &&
                                        !empty($order->getOrderPayments())
                                    ) {
                                        if (stripos($order->payment, 'comfino') !== false &&
                                            (
                                                $current_order_state->paid ||
                                                !empty(array_intersect(
                                                    $current_order_state->name,
                                                    $this->comfinoConfirmStates
                                                ))
                                            )
                                        ) {
                                            $this->errors[] = sprintf(
                                                Tools::displayError(
                                                    'Cancellation of accepted order #%d paid via '.
                                                    'Comfino service is not allowed.'
                                                ),
                                                $id_order
                                            );

                                            unset($_POST['orderBox'][$idx]);
                                        }
                                    }

                                    if (isset(Tools::getValue('orderBox')[$idx]) &&
                                        $order_state->id == Configuration::get('PS_OS_CANCELED')
                                    ) {
                                        ComfinoApi::cancelOrder($order->id);
                                    }
                                } else {
                                    $this->errors[] = Tools::displayError(
                                        'The order has already been assigned this status.'
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }

        parent::processBulkUpdateOrderStatus();
    }

    public function postProcess()
    {
        // If id_order is sent, we instantiate a new Order object
        if (Tools::isSubmit('id_order') && Tools::getValue('id_order') > 0) {
            $order = new Order(Tools::getValue('id_order'));

            if (!Validate::isLoadedObject($order)) {
                $this->errors[] = Tools::displayError('The order cannot be found within your database.');
            }

            ShopUrl::cacheMainDomainForShop((int) $order->id_shop);
        }

        /* Change order status, add a new entry in order history and send an e-mail to the customer if needed */
        if (Tools::isSubmit('submitState') && isset($order)) {
            if ($this->tabAccess['edit'] === '1') {
                $order_state = new OrderState(Tools::getValue('id_order_state'));

                if (!Validate::isLoadedObject($order_state)) {
                    $this->errors[] = Tools::displayError('The new order status is invalid.');
                } else {
                    $current_order_state = $order->getCurrentOrderState();

                    if ($current_order_state->id != $order_state->id) {
                        if ($current_order_state !== null && $current_order_state->id != $order_state->id &&
                            $order_state->id == Configuration::get('PS_OS_CANCELED') &&
                            !empty($order->getOrderPayments())
                        ) {
                            if (stripos($order->payment, 'comfino') !== false &&
                                (
                                    $current_order_state->paid ||
                                    !empty(array_intersect($current_order_state->name, $this->comfinoConfirmStates))
                                )
                            ) {
                                $this->errors[] = Tools::displayError(
                                    'Cancellation of accepted order paid via Comfino service is not allowed.'
                                );
                            }
                        }

                        if (empty($this->errors) && $order_state->id == Configuration::get('PS_OS_CANCELED')) {
                            ComfinoApi::cancelOrder($order->id);
                        }
                    } else {
                        $this->errors[] = Tools::displayError('The order has already been assigned this status.');
                    }
                }
            } else {
                $this->errors[] = Tools::displayError('You do not have permission to edit this.');
            }

            if (!empty($this->errors)) {
                // Disable default processing of the state submit in the base class if errors occurred
                unset($_POST['submitState']);
            }
        }

        parent::postProcess();
    }
}
