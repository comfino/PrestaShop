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

require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';

class ComfinoOrdersListController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;

        parent::__construct();
    }

    public function initContent()
    {
        $this->context->smarty->assign(['orders' => OrdersList::getAllOrders()]);

        if (COMFINO_PS_17) {
            $this->setTemplate('module:comfino/views/templates/admin/comfino_order_list.tpl');
        } else {
            $this->setTemplate('comfino_order_list.tpl');
        }
    }

    public function postProcess()
    {
        if (!empty($_POST['change_status'])) {
            $order = \Comfino\ApiClient::getOrder(Tools::getValue('self_link'));

            if ($order === false || !isset($order['status']) || !isset($order['orderId'])) {
                return false;
            }

            OrdersList::processState($order['orderId'], $order['status']);

            return true;
        }
    }
}
