<?php

namespace Comfino\Common\Frontend\TemplateRenderer;

interface RendererStrategyInterface
{
    public function renderPaywallTemplate(string $paywallContents): string;
    public function renderErrorTemplate(\Throwable $exception): string;
}
