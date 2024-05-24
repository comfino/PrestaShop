<?php

namespace Comfino;

use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Cache\StorageAdapter;
use Comfino\Common\Backend\Cache\Bucket;
use Comfino\Common\Backend\CacheManager;
use Comfino\Common\Frontend\PaywallRenderer;
use Comfino\Paywall\PaywallViewTypeEnum;
use Comfino\TemplateRenderer\ModuleRendererStrategy;
use Psr\Http\Client\ClientExceptionInterface;

class PaywallManager
{
    private const PAYWALL_GUI_FRAGMENTS = ['template', 'style', 'script', 'frontend_style', 'frontend_script'];

    public static function renderPaywall(\PaymentModule $module, LoanQueryCriteria $queryCriteria): string
    {
        $language = (\Context::getContext()->language->iso_code === 'pl') ? 'pl' : 'en';

        (new PaywallRenderer(
            ApiClient::getInstance(),
            CacheManager::getInstance()->getCacheBucket("paywall_$language", new StorageAdapter("paywall_$language")),
            new ModuleRendererStrategy()
        ))->renderPaywall($queryCriteria);

        $fragments = [];

        foreach (self::PAYWALL_GUI_FRAGMENTS as $fragment) {
            if (self::getCache()->has($fragment)) {
                $fragments[$fragment] = self::getCache()->get($fragment);
            }
        }

        if (count($fragments) < count(self::PAYWALL_GUI_FRAGMENTS)) {
            try {
                $fragments = self::fetchPaywallFragments();
            } catch (\Throwable $e) {
                ApiClient::processApiError('Paywall error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);

                return TemplateManager::render($module, 'api_error', 'front', ['error_message' => $e->getMessage()]);
            }
        }

        try {
            $paywallProductsList = ApiClient::getInstance()->getPaywall(
                $queryCriteria,
                new PaywallViewTypeEnum(PaywallViewTypeEnum::PAYWALL_VIEW_LIST)
            )->paywallPage;
        } catch (\Throwable $e) {
            ApiClient::processApiError('Paywall error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);

            return TemplateManager::render($module, 'api_error', 'front', ['error_message' => $e->getMessage()]);
        }

        return str_replace(
            ['{PAYWALL_STYLE}', '{PAYWALL_PRODUCTS_LIST}', '{PAYWALL_SCRIPT}'],
            [$fragments['style'], $paywallProductsList, $fragments['script']],
            $fragments['template']
        );
    }

    /**
     * @return string[]
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public static function fetchPaywallFragments(): array
    {
        return ApiClient::getInstance()->getPaywallFragments()->paywallFragments;
    }

    public static function clearCache(): void
    {
        self::getCache()->clear();
    }

    private static function getCache(): Bucket
    {
        $language = (\Context::getContext()->language->iso_code === 'pl') ? 'pl' : 'en';

        return CacheManager::getInstance()->getCacheBucket("paywall_$language", new StorageAdapter("paywall_$language"));
    }
}
