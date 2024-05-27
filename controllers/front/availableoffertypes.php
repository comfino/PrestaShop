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

use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\OrderManager;
use Comfino\SettingsManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoAvailableOfferTypesModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        ErrorLogger::init($this->module);

        parent::postProcess();

        $serializer = new JsonSerializer();
        $available_product_types = SettingsManager::getProductTypesStrings(ProductTypesListTypeEnum::LIST_TYPE_WIDGET);

        if (!Tools::getIsset('product_id')) {
            echo $serializer->serialize($available_product_types);
            exit;
        }

        $product = new Product(Tools::getValue('product_id'));

        if (!Validate::isLoadedObject($product)) {
            echo $serializer->serialize($available_product_types);
            exit;
        }

        echo $serializer->serialize(
            SettingsManager::getAllowedProductTypes('widget', OrderManager::getShopCartFromProduct($product))
        );

        exit;
    }
}
