<?php
/**
 * 2007-2021 PrestaShop
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
 * @author PrestaShop SA <contact@prestashop.com>
 * @copyright  2007-2021 PrestaShop SA
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @version  Release: $Revision$
 *  International Registered Trademark & Property of PrestaShop SA
 */

require_once _PS_MODULE_DIR_ . "comfino/models/OrdersList.php";
require_once _PS_MODULE_DIR_ . "comfino/src/Api.php";

class ComfinoOrdersListController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        $this->context->smarty->assign(
            [
                'orders' => OrdersList::getAllOrders()
            ]
        );

        $this->setTemplate('comfino_order_list.tpl');
    }

    public function postProcess()
    {
        if (!empty($_POST['change_status'])) {
            $data = json_decode(ComfinoApi::getOrder(Tools::getValue('self_link')), true);

            if (!isset($data['status']) || !isset($data['orderId'])) {
                return false;
            }

            OrdersList::processState($data['orderId'], $data['status']);

            return true;
        }
    }
}
