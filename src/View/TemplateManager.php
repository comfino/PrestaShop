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

namespace Comfino\View;

use Comfino\Main;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class TemplateManager
{
    public static function renderModuleView(string $name, string $path, array $variables = []): string
    {
        $templatePath = 'views/templates';

        if (!empty($path)) {
            $templatePath .= ('/' . trim($path, ' /'));
        }

        $templatePath .= "/$name.tpl";

        if (!empty($variables)) {
            \Context::getContext()->smarty->assign($variables);
        }

        $module = Main::getModule();

        if (method_exists($module, 'fetch')) {
            if (version_compare(_PS_VERSION_, '1.7.7.0', '<')) {
                // PrestaShop prior than 1.7.7.0 expects full absolute path to the template file.
                return $module->fetch(Main::getModulePath($templatePath));
            }

            return $module->fetch("module:$module->name/$templatePath");
        }

        return $module->display(Main::getModuleRelativePath("$module->name.php"), $templatePath);
    }

    public static function renderControllerView(
        \ModuleFrontController $frontController,
        string $name,
        string $path,
        array $variables = []
    ): void {
        $templatePath = 'views/templates';

        if (!empty($path)) {
            $templatePath .= ('/' . trim($path, ' /'));
        }

        $templatePath .= "/$name.tpl";

        if (!empty($variables)) {
            \Context::getContext()->smarty->assign($variables);
        }

        try {
            if (COMFINO_PS_17) {
                $frontController->setTemplate("module:{$frontController->module->name}/$templatePath");
            } else {
                $frontController->setTemplate("$name.tpl");
            }
        } catch (\Exception $e) {
            FrontendManager::processError('Template rendering error', $e, 500, 'Template rendering error.');
        }
    }
}
