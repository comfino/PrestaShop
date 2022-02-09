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

        if (Tools::isSubmit('id_order') && Tools::getValue('id_order') > 0) {
            $order = new Order(Tools::getValue('id_order'));

            if (!Validate::isLoadedObject($order)) {
                $this->errors[] = Tools::displayError('The order cannot be found within your database.');
            }

            ShopUrl::cacheMainDomainForShop((int) $order->id_shop);
        }

        foreach ($this->statuses_array as $statusId => $statusName) {
            if (stripos($statusName, 'comfino') !== false) {
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
}
