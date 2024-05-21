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

namespace Comfino;

class FormManager
{
    public static function getHelperForm(
        \PaymentModule $module,
        string $submit_action,
        ?string $form_template_dir = null,
        ?string $form_template = null
    ): \HelperForm
    {
        $helper = new \HelperForm();
        $language = (int) \Configuration::get('PS_LANG_DEFAULT');

        $helper->module = $module;
        $helper->name_controller = $module->name;
        $helper->token = \Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = \AdminController::$currentIndex . '&configure=' . $module->name;

        // Language
        $helper->default_form_language = $language;
        $helper->allow_employee_form_lang = $language;

        // Title and toolbar
        $helper->title = $module->displayName;
        $helper->show_toolbar = true; // false -> remove toolbar
        $helper->toolbar_scroll = true; // yes - > Toolbar is always visible at the top of the screen.
        $helper->submit_action = $submit_action;
        $helper->toolbar_btn = [
            'save' => [
                'desc' => $module->l('Save'),
                'href' => \AdminController::$currentIndex . '&configure=' . $module->name . '&save' . $module->name .
                    '&token=' . \Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => \AdminController::$currentIndex . '&token=' . \Tools::getAdminTokenLite('AdminModules'),
                'desc' => $module->l('Back to list'),
            ],
        ];

        if ($form_template !== null && $form_template_dir !== null) {
            $helper->base_folder = $form_template_dir;
            $helper->base_tpl = $form_template;
        }

        return $helper;
    }
}
