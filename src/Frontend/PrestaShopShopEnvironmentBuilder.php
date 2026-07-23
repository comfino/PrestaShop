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

namespace Comfino\Frontend;

use Comfino\Api\Dto\Plugin\ShopTheme;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * PrestaShop implementation of AbstractShopEnvironmentBuilder.
 *
 * Reports the active PrestaShop theme name as the theme code and resolves the family via the registered
 * ThemeFamilyRules, defaulting to 'classic' (the classic jQuery-based PrestaShop stack) when no rule matches.
 */
class PrestaShopShopEnvironmentBuilder extends AbstractShopEnvironmentBuilder
{
    /**
     * {@inheritDoc}
     */
    protected function getPlatformIdentifier(): string
    {
        return 'prestashop';
    }

    /**
     * {@inheritDoc}
     */
    protected function getPlatformName(): string
    {
        return 'PrestaShop';
    }

    /**
     * {@inheritDoc}
     *
     * PrestaShop has no commercial-edition concept exposed here.
     */
    protected function detectEdition(): ?string
    {
        return null;
    }

    /**
     * {@inheritDoc}
     *
     * Reads the active theme name from the shop context (PrestaShop 1.7+).
     */
    protected function detectTheme(): ShopTheme
    {
        $code = '';

        try {
            $context = \Context::getContext();

            if ($context !== null && $context->shop !== null && isset($context->shop->theme_name)) {
                $code = (string) $context->shop->theme_name;
            }
        } catch (\Throwable $e) {
            $code = '';
        }

        $family = $this->rules->resolveFamily($code !== '' ? [$code] : []);

        if ($family === 'custom') {
            $family = 'classic';
        }

        return new ShopTheme($code, $family, []);
    }
}
