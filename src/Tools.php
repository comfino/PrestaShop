<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
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
     * @param float $price
     * @param int $id_currency
     *
     * @return float|string
     *
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public function formatPrice($price, $id_currency)
    {
        return COMFINO_PS_17 && $this->locale !== null
            ? $this->locale->formatPrice($price, $this->getCurrencyIsoCode($id_currency))
            : \Tools::displayPrice($price);
    }

    /**
     * @param int $id_currency
     *
     * @return string
     */
    public function getCurrencyIsoCode($id_currency)
    {
        return (new \Currency($id_currency))->iso_code;
    }

    /**
     * @return \Cookie
     */
    public function getCookie()
    {
        return $this->context->cookie;
    }
}