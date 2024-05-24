<?php

namespace Comfino;

class TemplateManager
{
    public static function renderModuleView(
        \PaymentModule $module,
        string $name,
        string $path,
        array $variables = []
    ): string
    {
        $templatePath = "views/templates/$path/$name.tpl";

        if (!empty($variables)) {
            $module->smarty->assign($variables);
        }

        if (method_exists($module, 'fetch')) {
            return $module->fetch("module:$module->name/$templatePath");
        }

        return $module->display(__FILE__, $templatePath);
    }

    public static function renderControllerView(
        \ModuleFrontController $frontController,
        string $name,
        string $path,
        array $variables = []
    ): void
    {
        $templatePath = "views/templates/$path/$name.tpl";

        if (!empty($variables)) {
            $frontController->context->smarty->assign($variables);
        }

        if (COMFINO_PS_17) {
            $frontController->setTemplate("module:{$frontController->module->name}/$templatePath");
        } else {
            $frontController->setTemplate('payment_error_16.tpl');
        }
    }
}
