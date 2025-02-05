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

namespace Comfino;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Tools
{
    /**
     * @var \ContextCore|\Context
     */
    private $context;

    /**
     * @var \PrestaShop\PrestaShop\Core\Localization\Locale
     */
    private $locale;

    public function __construct(\Context $context)
    {
        $this->context = $context;

        if (COMFINO_PS_17 && isset($this->context->currentLocale)) {
            $this->locale = $this->context->currentLocale;
        }
    }

    /**
     * @return float|string
     *
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function formatPrice(float $price, int $currencyId)
    {
        return COMFINO_PS_17 && $this->locale !== null
            ? $this->locale->formatPrice($price, $this->getCurrencyIsoCode($currencyId))
            : \Tools::displayPrice($price);
    }

    public function getCurrencyIsoCode(int $currencyId): string
    {
        if ($currencyId === 0) {
            return 'PLN';
        }

        return COMFINO_PS_17 && method_exists(\Currency::class, 'getIsoCodeById')
            ? \Currency::getIsoCodeById($currencyId)
            : \Currency::getCurrencyInstance($currencyId)->iso_code;
    }

    public function getLanguageIsoCode(int $langId): string
    {
        return \Language::getIsoById($langId);
    }

    public function getCountryIsoCode(int $countryId): string
    {
        return \Country::getIsoById($countryId);
    }

    public function getCookie(): \Cookie
    {
        return $this->context->cookie;
    }

    public function getCurrentCurrencyId(): int
    {
        return $this->context->cookie->id_currency;
    }

    /**
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function getFormattedPrice(float $price): float
    {
        return (float) preg_replace(
            ['/[^\d,.]/', '/(?<=\d),(?=\d{3}(?:[^\d]|$))/', '/,00$/', '/,/'],
            ['', '', '', '.'],
            $this->formatPrice($price, $this->getCurrentCurrencyId())
        );
    }
}
