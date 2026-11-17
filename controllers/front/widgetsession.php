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

use Comfino\Configuration\ConfigManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * GET comfino/widgetsession: the per-visitor values of the product widget (track ID, logging token), which must not be
 * embedded in full-page-cached product pages. Never cached.
 */
class ComfinoWidgetSessionModuleFrontController extends ModuleFrontController
{
    public function postProcess(): void
    {
        parent::postProcess();

        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true);
        header('Pragma: no-cache', true);
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT', true);

        $values = [];

        if ($this->module instanceof Comfino && $this->module->active && ConfigManager::isWidgetEnabled()) {
            $values = array_filter(
                ConfigManager::getWidgetSessionValues(),
                static function ($value) {
                    return $value !== null;
                }
            );
        }

        exit(json_encode((object) $values));
    }
}
