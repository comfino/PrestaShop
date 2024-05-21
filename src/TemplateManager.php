<?php

namespace Comfino;

class TemplateManager
{
    public static function render(\PaymentModule $module, string $name, string $path, array $variables = []): string
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
}
