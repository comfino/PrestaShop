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
require_once _PS_MODULE_DIR_ . 'comfino/models/OrdersList.php';

class ComfinoNotifyModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        Api::init();
        ErrorLogger::init();

        parent::postProcess();

        $json_data = Tools::file_get_contents('php://input');
        $hash_algorithm = $this->getHashAlgorithm();
        $hash_algos = array_intersect(array_merge(['sha3-256'], PHP_VERSION_ID < 70100 ? ['sha512'] : []), hash_algos());

        if (in_array($hash_algorithm, $hash_algos, true)) {
            if ($this->getSignature() !== hash($hash_algorithm, Api::getApiKey() . $json_data)) {
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

        if (!\Comfino\OrdersList::processState($data['externalId'], $data['status'])) {
            exit($this->setResponse(400, sprintf('Invalid status %s.', $data['status'])));
        }

        exit($this->setResponse(200, 'OK'));
    }

    private function getSignature()
    {
        return isset($_SERVER['HTTP_CR_SIGNATURE']) ? $_SERVER['HTTP_CR_SIGNATURE'] : '';
    }

    private function getHashAlgorithm()
    {
        return isset($_SERVER['HTTP_CR_SIGNATURE_ALGO']) ? $_SERVER['HTTP_CR_SIGNATURE_ALGO'] : 'sha3-256';
    }

    private function setResponse($code, $content)
    {
        http_response_code($code);
        header('Content-Type: application/json');

        return json_encode(['status' => $content]);
    }
}
