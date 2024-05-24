<?php

namespace Comfino\TemplateRenderer;

use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;

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
        // TODO: Implement renderPaywallTemplate() method.
    }

    /**
     * @inheritDoc
     */
    public function renderErrorTemplate($exception): string
    {
        // TODO: Implement renderErrorTemplate() method.
    }
}
