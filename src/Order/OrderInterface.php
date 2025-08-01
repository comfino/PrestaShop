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

use Comfino\Api\CartInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface OrderInterface
{
    /**
     * Shop internal order ID.
     *
     * @return string
     */
    public function getId();

    /**
     * Callback URL used by Comfino API for sending notifications about transaction status changes.
     *
     * @return string|null
     */
    public function getNotifyUrl();

    /**
     * Return URL to the shop confirmation page, where customer will be redirected from Comfino website when transaction will be finished - successfully or not.
     *
     * @return string
     */
    public function getReturnUrl();

    /** @return LoanParametersInterface */
    public function getLoanParameters();

    /** @return CartInterface */
    public function getCart();

    /** @return CustomerInterface */
    public function getCustomer();

    /** @return SellerInterface|null */
    public function getSeller();

    /** @return string|null */
    public function getAccountNumber();

    /** @return string|null */
    public function getTransferTitle();
}
