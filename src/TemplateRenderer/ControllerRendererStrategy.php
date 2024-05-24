<?php

namespace Comfino\TemplateRenderer;

use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;

class ControllerRendererStrategy implements RendererStrategyInterface
{
    /** @var \ModuleFrontController */
    private $frontController;

    public function __construct(\ModuleFrontController $frontController)
    {
        $this->frontController = $frontController;
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
