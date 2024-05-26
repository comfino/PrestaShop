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

use Comfino\ApiClient;
use Comfino\ErrorLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoTransactionStatusModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        ErrorLogger::init($this->module);

        parent::postProcess();

        $json_data = Tools::file_get_contents('php://input');
        $hash_algorithm = $this->getHashAlgorithm();

        if (in_array($hash_algorithm, ApiClient::getHashAlgos(), true)) {
            if (!hash_equals(hash($hash_algorithm, ApiClient::getApiKey() . $json_data), $this->getSignature())) {
                exit($this->setResponse(400, 'Failed comparison of CR-Signature and shop hash.'));
            }
        } else {
            exit($this->setResponse(403, 'Unsupported hash algorithm.'));
        }

        $data = json_decode($json_data, true);

        if (!isset($data['externalId'])) {
            exit($this->setResponse(400, 'External ID must be set.'));
        }

        if (!isset($data['status'])) {
            exit($this->setResponse(400, 'Status must be set.'));
        }

        if ($data['status'] === \Comfino\OrderManager::CANCELLED_BY_SHOP) {
            exit($this->setResponse(400, 'Invalid status ' . \Comfino\OrderManager::CANCELLED_BY_SHOP . '.'));
        }

        if (!\Comfino\OrderManager::processState($data['externalId'], $data['status'])) {
            exit($this->setResponse(400, sprintf('Invalid status %s.', $data['status'])));
        }

        exit($this->setResponse(200, 'OK'));
    }

    private function getSignature()
    {
        if (isset($_SERVER['HTTP_CR_SIGNATURE'])) {
            return $_SERVER['HTTP_CR_SIGNATURE'];
        }

        return isset($_SERVER['HTTP_X_CR_SIGNATURE']) ? $_SERVER['HTTP_X_CR_SIGNATURE'] : '';
    }

    private function getHashAlgorithm()
    {
        if (isset($_SERVER['HTTP_CR_SIGNATURE_ALGO'])) {
            return $_SERVER['HTTP_CR_SIGNATURE_ALGO'];
        }

        return isset($_SERVER['HTTP_X_CR_SIGNATURE_ALGO']) ? $_SERVER['HTTP_X_CR_SIGNATURE_ALGO'] : 'sha3-256';
    }

    private function setResponse($code, $content)
    {
        http_response_code($code);
        header('Content-Type: application/json');

        return json_encode(['status' => $content]);
    }
}
