<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

use desktopd\SHA3\Sponge as SHA3;

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

        if ($this->getSignature() !== $this->hash(ComfinoApi::getApiKey() . $jsonData)) {
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

    private function hash($inputString)
    {
        $hash = null;

        if (in_array('sha3-256', hash_algos(), true)) {
            $hash = hash('sha3-256', $inputString);
        } else {
            require_once _PS_MODULE_DIR_ . 'comfino/lib/php-sha3-streamable/namespaced/desktopd/SHA3/Sponge.php';

            $sponge = SHA3::init(SHA3::SHA3_256);
            $sponge->absorb($inputString);

            $hash = bin2hex($sponge->squeeze());
        }

        return $hash;
    }
}
