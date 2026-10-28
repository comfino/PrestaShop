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

namespace Comfino\Order;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class PromoCode
{
    const MAX_LENGTH = 64;
    const REQUEST_FIELD = 'comfino_promo_code';

    /**
     * @param mixed $value
     *
     * @return string|null
     */
    public static function normalize($value)
    {
        if (!is_string($value)) {
            return null;
        }

        $code = html_entity_decode(strip_tags($value));
        $trimmed = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $code);
        $code = $trimmed !== null ? $trimmed : trim($code);

        if ($code === '' || mb_strlen($code) > self::MAX_LENGTH) {
            return null;
        }

        return $code;
    }
}
