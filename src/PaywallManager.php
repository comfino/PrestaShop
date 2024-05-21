<?php

namespace Comfino;

use Comfino\Cache\StorageAdapter;
use Comfino\Common\Backend\Cache\Bucket;
use Comfino\Common\Backend\CacheManager;
use Psr\Http\Client\ClientExceptionInterface;

class PaywallManager
{
    private const PAYWALL_GUI_FRAGMENTS = ['template', 'style', 'script', 'frontend_style', 'frontend_script'];

    public static function renderPaywall(\PaymentModule $module): string
    {
        $fragments = [];

        foreach (self::PAYWALL_GUI_FRAGMENTS as $fragment) {
            if (self::getCache()->has($fragment)) {
                $fragments[$fragment] = self::getCache()->get($fragment);
            }
        }

        if (count($fragments) < count(self::PAYWALL_GUI_FRAGMENTS)) {
            try {
                $fragments = ApiClient::getInstance()->getPaywallFragments()->paywallFragments;
            } catch (\Throwable $e) {
                ApiClient::processApiError('Paywall error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);

                return TemplateManager::render($module, 'api_error', 'front', ['error_message' => $e->getMessage()]);
            }
        }

        $paywallView = str_replace(
            ['{PAYWALL_STYLE}', '{PAYWALL_PRODUCTS_LIST}', '{PAYWALL_SCRIPT}'],
            [],
            $fragments['template']
        );

        return $paywallView;
    }

    public static function clearCache(): void
    {
        self::getCache()->clear();
    }

    private static function getCache(): Bucket
    {
        return CacheManager::getInstance()->getCacheBucket('paywall', new StorageAdapter('paywall'));
    }
}
