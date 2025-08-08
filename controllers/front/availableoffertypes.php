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
require_once _PS_MODULE_DIR_ . 'comfino/src/ErrorLogger.php';

use Comfino\Api;
use Comfino\ErrorLogger;

class ComfinoAvailableOfferTypesModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        Api::init($this->module);
        ErrorLogger::init();

        parent::postProcess();

        $config_manager = new \Comfino\ConfigManager($this->module);
        $available_product_types = array_map(
            static function (array $offer_type) { return $offer_type['key']; },
            $config_manager->getOfferTypes()
        );

        if (!Tools::getIsset('product_id')) {
            echo json_encode($available_product_types);
            exit;
        }

        $product = new \Product(Tools::getValue('product_id'));

        if (!\Validate::isLoadedObject($product)) {
            echo json_encode($available_product_types);
            exit;
        }

        $filtered_product_types = [];

        foreach ($available_product_types as $product_type) {
            if ($config_manager->isFinancialProductAvailable($product_type, [$product->getFields()])) {
                $filtered_product_types[] = $product_type;
            }
        }

        echo json_encode($filtered_product_types);

        exit;
    }
}
