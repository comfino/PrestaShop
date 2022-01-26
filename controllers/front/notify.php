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

if (!defined('_PS_VERSION_')) {
    return;
}

require_once _PS_MODULE_DIR_.'comfino/models/OrdersList.php';

class ComfinoNotifyModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        parent::postProcess();

        $data = json_decode(Tools::file_get_contents('php://input'), true);
        if (!isset($data['externalId'])) {
            die("External ID must be set");
        }

        if (!isset($data['status'])) {
            die("Status must be set");
        }

        OrdersList::processState($data['externalId'], $data['status']);

        exit(true);
    }
}
