<?php

namespace Comfino\TemplateRenderer;

use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\TemplateManager;

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
        return $paywallContents;
    }

    /**
     * @inheritDoc
     */
    public function renderErrorTemplate($exception): string
    {
        TemplateManager::renderControllerView($this->frontController, '', '', ['error' => $exception->getMessage()]);

        return '';
    }
}
