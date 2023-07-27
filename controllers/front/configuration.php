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
use Comfino\ConfigManager;
use Comfino\ErrorLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ConfigManager.php';

class ComfinoConfigurationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        Api::init();
        ErrorLogger::init();

        parent::postProcess();

        $config_manager = new ConfigManager();

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                if (!Tools::getIsset('vkey')) {
                    exit($this->setResponse(403, 'Access not allowed.'));
                }

                $verification_key = Tools::getValue('vkey');

                $hash_algorithm = $this->getHashAlgorithm();
                $hash_algos = array_intersect(array_merge(['sha3-256'], PHP_VERSION_ID < 70100 ? ['sha512'] : []), hash_algos());

                if (in_array($hash_algorithm, $hash_algos, true)) {
                    if ($this->getSignature() !== hash($hash_algorithm, Api::getApiKey() . $verification_key)) {
                        exit($this->setResponse(400, 'Failed comparison of CR-Signature and shop hash.'));
                    }
                } else {
                    exit($this->setResponse(403, 'Unsupported hash algorithm.'));
                }

                $response = [
                    'shop_info' => [
                        'plugin_version' => COMFINO_VERSION,
                        'shop_version' => _PS_VERSION_,
                        'symfony_version' => COMFINO_PS_17 && class_exists('\Symfony\Component\HttpKernel\Kernel')
                            ? \Symfony\Component\HttpKernel\Kernel::VERSION
                            : 'n/a',
                        'php_version' => PHP_VERSION,
                        'server_software' => $_SERVER['SERVER_SOFTWARE'],
                        'server_name' => $_SERVER['SERVER_NAME'],
                        'server_addr' => $_SERVER['SERVER_ADDR'],
                        'database_version' => Db::getInstance()->getVersion(),
                    ],
                    'shop_configuration' => $config_manager->returnConfigurationOptions(),
                ];

                exit($this->setResponse(200, 'OK', $response));

            case 'POST':
            case 'PUT':
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

                $configuration_options = json_decode($json_data, true);

                if (is_array($configuration_options)) {
                    $config_manager->updateConfiguration($configuration_options);

                    exit($this->setResponse(204, ''));
                }

                exit($this->setResponse(400, 'Wrong input data.'));

            default:
                exit($this->setResponse(403, 'Access not allowed.'));
        }
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

    /**
     * @param $code
     * @param $status_message
     * @param array|null $data
     * @return mixed
     */
    private function setResponse($code, $status_message, $data = null)
    {
        http_response_code($code);
        header('Content-Type: application/json');

        $response = ['status' => $status_message];

        if ($data !== null) {
            $response = array_merge($response, $data);
        }

        return json_encode($response);
    }
}
