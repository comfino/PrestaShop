<?php

namespace Comfino\TemplateRenderer;

use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\TemplateManager;

class ModuleRendererStrategy implements RendererStrategyInterface
{
    /** @var \PaymentModule */
    private $module;

    public function __construct(\PaymentModule $module)
    {
        $this->module = $module;
    }

    /**
     * @inheritDoc
     */
    public function renderPaywallTemplate($paywallContents): string
    {
        return $paywallContents;
    }

    /**
     * @inheritDoc
     */
    public function renderErrorTemplate($exception): string
    {
        return TemplateManager::renderModuleView(
            $this->module,
            'error',
            'front',
            ['error' => $exception->getMessage()]
        );
    }
}
