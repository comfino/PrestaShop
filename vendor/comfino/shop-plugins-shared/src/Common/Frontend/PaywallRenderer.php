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

namespace Comfino\Common\Frontend;

use Comfino\Api\Client;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface;
use Comfino\Paywall\PaywallViewTypeEnum;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;

final class PaywallRenderer
{
    /**
     * @readonly
     * @var \Comfino\Api\Client
     */
    private $client;
    /**
     * @readonly
     * @var \Psr\Cache\CacheItemPoolInterface
     */
    private $cache;
    /**
     * @readonly
     * @var \Comfino\Common\Frontend\TemplateRenderer\RendererStrategyInterface
     */
    private $rendererStrategy;
    private const PAYWALL_GUI_FRAGMENTS = ['template', 'style', 'script', 'frontend_style', 'frontend_script'];

    public function __construct(Client $client, CacheItemPoolInterface $cache, RendererStrategyInterface $rendererStrategy)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->rendererStrategy = $rendererStrategy;
    }

    public function renderPaywall(LoanQueryCriteria $queryCriteria): string
    {
        $language = $this->client->getApiLanguage();
        $fragments = [];

        foreach (self::PAYWALL_GUI_FRAGMENTS as $fragmentName) {
            try {
                $itemKey = $this->getItemKey($fragmentName, $language);

                if ($this->cache->getItem($itemKey)->isHit()) {
                    $fragments[$fragmentName] = $this->cache->getItem($itemKey)->get();
                }
            } catch (InvalidArgumentException $exception) {
            }
        }

        if (count($fragments) < count(self::PAYWALL_GUI_FRAGMENTS)) {
            try {
                $fragments = $this->fetchPaywallFragments();

                foreach ($fragments as $fragmentName => $fragmentContents) {
                    $this->cache->saveDeferred(
                        $this->cache->getItem($this->getItemKey($fragmentName, $language))->set($fragmentContents)
                    );
                }

                $this->cache->commit();
            } catch (\Throwable $e) {
                return $this->rendererStrategy->renderErrorTemplate($e);
            }
        }

        try {
            $paywallProductsList = $this->client->getPaywall(
                $queryCriteria,
                new PaywallViewTypeEnum(PaywallViewTypeEnum::PAYWALL_VIEW_LIST)
            )->paywallPage;

            return $this->rendererStrategy->renderPaywallTemplate(
                str_replace(
                    ['{PAYWALL_STYLE}', '{PAYWALL_PRODUCTS_LIST}', '{PAYWALL_SCRIPT}'],
                    [$fragments['style'], $paywallProductsList, $fragments['script']],
                    $fragments['template']
                )
            );
        } catch (\Throwable $e) {
            return $this->rendererStrategy->renderErrorTemplate($e);
        }
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
    public function fetchPaywallFragments(): array
    {
        return $this->client->getPaywallFragments()->paywallFragments;
    }

    private function getItemKey(string $fragmentName, string $language): string
    {
        return "comfino_paywall:$fragmentName" . ($fragmentName === 'template' ? ":$language" : '');
    }
}
