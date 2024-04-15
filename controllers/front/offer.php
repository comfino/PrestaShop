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

use Comfino\Api;
use Comfino\ErrorLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Tools.php';

class ComfinoOfferModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        Api::init($this->module);
        ErrorLogger::init();

        parent::postProcess();

        $cookie = (new \Comfino\Tools($this->context))->getCookie();
        $cookie->loan_amount = Tools::getValue('loan_amount');
        $cookie->loan_type = Tools::getValue('loan_type');
        $cookie->loan_term = Tools::getValue('loan_term');
        $cookie->write();

        echo json_encode(['status' => 'OK', 'type' => $cookie->loan_type, 'term' => (int) $cookie->loan_term]);

        exit;
    }
}
