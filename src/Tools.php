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

        if (COMFINO_PS_17) {
            $this->locale = $this->context->currentLocale;
        }
    }

    /**
     * @return float|string
     *
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function formatPrice(float $price, int $id_currency)
    {
        return COMFINO_PS_17 && $this->locale !== null
            ? $this->locale->formatPrice($price, $this->getCurrencyIsoCode($id_currency))
            : \Tools::displayPrice($price);
    }

    public function getCurrencyIsoCode(int $id_currency): string
    {
        return COMFINO_PS_17 && method_exists(\Currency::class, 'getIsoCodeById')
            ? \Currency::getIsoCodeById($id_currency)
            : \Currency::getCurrencyInstance($id_currency)->iso_code;
    }

    public function getLanguageIsoCode(int $id_lang): string
    {
        return \Language::getIsoById($id_lang);
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
            ['/[^\d,.]/', '/,/'],
            ['', '.'],
            $this->formatPrice($price, $this->getCurrentCurrencyId())
        );
    }

    public function isValidTaxId(string $tax_id): bool
    {
        if (empty($tax_id) || strlen($tax_id) !== 10 || !preg_match('/^\d+$/', $tax_id)) {
            return false;
        }

        $arr_steps = [6, 5, 7, 2, 3, 4, 5, 6, 7];
        $int_sum = 0;

        for ($i = 0; $i < 9; ++$i) {
            $int_sum += $arr_steps[$i] * $tax_id[$i];
        }

        $int = $int_sum % 11;

        return ($int === 10 ? 0 : $int) === (int) $tax_id[9];
    }
}
