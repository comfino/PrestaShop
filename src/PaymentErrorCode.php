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

/**
 * Catalogue of the error conditions the customer-facing error page can display.
 *
 * The codes travel in the URL of the error page; the page resolves them to a translated message and ignores
 * anything it does not recognize. Free text is deliberately never passed through the request, so the shop's
 * own error page cannot be used to show attacker-chosen content on the shop's domain.
 */
class PaymentErrorCode
{
    /** The order was created in the shop, but the financing application could not be started. */
    const ORDER_CREATION = 'order_creation';

    /** Comfino declined to start the financing application. */
    const PAYMENT_REJECTED = 'payment_rejected';

    /** The Comfino API could not be reached. */
    const SERVICE_UNAVAILABLE = 'service_unavailable';
}
