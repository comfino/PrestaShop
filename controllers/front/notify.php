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
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';
require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

class ComfinoNotifyModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        ErrorLogger::init();

        parent::postProcess();

        $jsonData = Tools::file_get_contents('php://input');

        if ($this->getSignature() !== hash('sha3-256', Configuration::get('COMFINO_API_KEY') . $jsonData)) {
            exit($this->setResponse(400, 'Failed comparison of CR-Signature and shop hash.'));
        }

        $data = json_decode($jsonData, true);

        if (!isset($data['externalId'])) {
            exit($this->setResponse(400, 'External ID must be set.'));
        }

        if (!isset($data['status'])) {
            exit($this->setResponse(400, 'Status must be set.'));
        }

        OrdersList::processState($data['externalId'], $data['status']);

        exit($this->setResponse(200, 'OK'));
    }

    private function getSignature()
    {
        return isset($_SERVER['HTTP_CR_SIGNATURE']) ? $_SERVER['HTTP_CR_SIGNATURE'] : '';
    }

    private function setResponse($code, $content)
    {
        http_response_code($code);
        header('Content-Type: application/json');

        return json_encode(['status' => $content]);
    }
}
