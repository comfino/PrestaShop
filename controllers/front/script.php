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

use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Common\Frontend\WidgetInitScriptHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\ErrorLogger;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ComfinoScriptModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        ErrorLogger::init($this->module);

        parent::postProcess();

        header('Content-Type: application/javascript');

        if (ConfigManager::getConfigurationValue('COMFINO_WIDGET_ENABLED')) {
            if (($productId = Tools::getValue('product_id', null)) !== null) {
                $productId = (int) $productId;
            }

            try {
                $widgetVariables = ConfigManager::getWidgetVariables($this->module, $productId);

                echo WidgetInitScriptHelper::renderWidgetInitScript(
                    ConfigManager::getCurrentWidgetCode($this->module, $productId),
                    array_combine(
                        [
                            'WIDGET_KEY',
                            'WIDGET_PRICE_SELECTOR',
                            'WIDGET_TARGET_SELECTOR',
                            'WIDGET_PRICE_OBSERVER_SELECTOR',
                            'WIDGET_PRICE_OBSERVER_LEVEL',
                            'WIDGET_TYPE',
                            'OFFER_TYPE',
                            'EMBED_METHOD',
                        ],
                        ConfigManager::getConfigurationValues(
                            'widget_settings',
                            [
                                'COMFINO_WIDGET_KEY',
                                'COMFINO_WIDGET_PRICE_SELECTOR',
                                'COMFINO_WIDGET_TARGET_SELECTOR',
                                'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
                                'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
                                'COMFINO_WIDGET_TYPE',
                                'COMFINO_WIDGET_OFFER_TYPE',
                                'COMFINO_WIDGET_EMBED_METHOD',
                            ]
                        )
                    ),
                    $widgetVariables
                );
            } catch (Throwable $e) {
                ErrorLogger::sendError(
                    'Widget script endpoint',
                    $e->getCode(),
                    $e->getMessage(),
                    $e instanceof HttpErrorExceptionInterface ? $e->getUrl() : null,
                    $e instanceof HttpErrorExceptionInterface ? $e->getRequestBody() : null,
                    $e instanceof HttpErrorExceptionInterface ? $e->getResponseBody() : null,
                    $e->getTraceAsString()
                );
            }
        }

        exit;
    }
}
