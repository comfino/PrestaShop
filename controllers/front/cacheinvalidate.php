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

require_once _PS_MODULE_DIR_ . 'comfino/src/Api.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ApiCache.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ConfigManager.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';

use Comfino\Api;
use Comfino\ApiCache;
use Comfino\ConfigManager;
use Comfino\ErrorLogger;

/**
 * Receives the cache invalidation requests of the ComfinoPay backend: a signed POST of a JSON array of item names.
 * It only drops cached data, never writes any, and answers without a body.
 */
class ComfinoCacheinvalidateModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        Api::init($this->module);
        ErrorLogger::init();

        parent::postProcess();

        if (\Comfino\Tools::getServerValue('REQUEST_METHOD', '') !== 'POST') {
            header('Allow: POST');

            $this->sendResponse(405);
        }

        $json_data = Tools::file_get_contents('php://input');

        if (!in_array($this->getHashAlgorithm(), Api::getHashAlgos(), true) || !Api::isValidSignature($json_data, $this->getSignature())) {
            $this->sendResponse(403);
        }

        $item_names = json_decode($json_data, true);

        if (!is_array($item_names)) {
            $this->sendResponse(400);
        }

        foreach ($item_names as $item_name) {
            if (!is_string($item_name)) {
                continue;
            }

            switch ($item_name) {
                case 'remote_flags':
                    // The next checkout render fetches the flag attributes (the shop promotion) again.
                    Configuration::updateGlobalValue(ConfigManager::REMOTE_FLAG_ATTRIBUTES_EXPIRES_AT, 0);
                    break;

                case 'admin_product_types':
                    ApiCache::delete(Api::cacheKey('product_types_paywall'));
                    ApiCache::delete(Api::cacheKey('product_types_widget'));
                    break;

                default:
                    /* Widget types are not cached by this module, the paywall_* items are deprecated, and names
                       unknown to this version are ignored. */
                    break;
            }
        }

        $this->sendResponse(204);
    }

    private function getSignature()
    {
        if (isset($_SERVER['HTTP_CR_SIGNATURE'])) {
            return (string) $_SERVER['HTTP_CR_SIGNATURE'];
        }

        return isset($_SERVER['HTTP_X_CR_SIGNATURE']) ? (string) $_SERVER['HTTP_X_CR_SIGNATURE'] : '';
    }

    private function getHashAlgorithm()
    {
        if (isset($_SERVER['HTTP_CR_SIGNATURE_ALGO'])) {
            return (string) $_SERVER['HTTP_CR_SIGNATURE_ALGO'];
        }

        return isset($_SERVER['HTTP_X_CR_SIGNATURE_ALGO']) ? (string) $_SERVER['HTTP_X_CR_SIGNATURE_ALGO'] : 'sha3-256';
    }

    /**
     * @param int $code
     *
     * @return void
     */
    private function sendResponse($code)
    {
        http_response_code($code);

        exit;
    }
}
