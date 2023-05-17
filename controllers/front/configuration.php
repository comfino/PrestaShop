<?php
/**
 * 2007-2023 PrestaShop
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
 *  @copyright 2007-2023 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/ConfigManager.php';

class ComfinoNotifyModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        parent::postProcess();

        $config_manager = new ConfigManager();

        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                if (!Tools::getIsset('vkey')) {
                    exit($this->setResponse(403, 'Access not allowed.'));
                }

                $verification_key = Tools::getValue('vkey');

                $hashAlgorithm = $this->getHashAlgorithm();
                $hashAlgos = array_intersect(array_merge(['sha3-256'], PHP_VERSION_ID < 70100 ? ['sha512'] : []), hash_algos());

                if (in_array($hashAlgorithm, $hashAlgos, true)) {
                    if ($this->getSignature() !== hash($hashAlgorithm, ComfinoApi::getApiKey() . $verification_key)) {
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
                $jsonData = Tools::file_get_contents('php://input');
                $hashAlgorithm = $this->getHashAlgorithm();
                $hashAlgos = array_intersect(array_merge(['sha3-256'], PHP_VERSION_ID < 70100 ? ['sha512'] : []), hash_algos());

                if (in_array($hashAlgorithm, $hashAlgos, true)) {
                    if ($this->getSignature() !== hash($hashAlgorithm, ComfinoApi::getApiKey() . $jsonData)) {
                        exit($this->setResponse(400, 'Failed comparison of CR-Signature and shop hash.'));
                    }
                } else {
                    exit($this->setResponse(403, 'Unsupported hash algorithm.'));
                }

                $configuration_options = json_decode($jsonData, true);

                if (!is_array($configuration_options)) {
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
        return isset($_SERVER['HTTP_CR_SIGNATURE']) ? $_SERVER['HTTP_CR_SIGNATURE'] : '';
    }

    private function getHashAlgorithm()
    {
        return isset($_SERVER['HTTP_CR_SIGNATURE_ALGO']) ? $_SERVER['HTTP_CR_SIGNATURE_ALGO'] : 'sha3-256';
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

        return json_encode(['status' => $status_message]);
    }
}
