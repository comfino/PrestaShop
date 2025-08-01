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

require_once 'OrderInterface.php';
require_once 'LoanParametersInterface.php';
require_once 'CartInterface.php';
require_once 'CustomerInterface.php';
require_once 'SellerInterface.php';

class Order implements OrderInterface
{
    /**
     * @var string
     *
     * @readonly
     */
    private $id;

    /**
     * @var string
     *
     * @readonly
     */
    private $returnUrl;

    /**
     * @var LoanParametersInterface
     *
     * @readonly
     */
    private $loanParameters;

    /**
     * @var CartInterface
     *
     * @readonly
     */
    private $cart;

    /**
     * @var CustomerInterface
     *
     * @readonly
     */
    private $customer;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $notifyUrl;

    /**
     * @var SellerInterface|null
     *
     * @readonly
     */
    private $seller;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $accountNumber;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $transferTitle;

    /**
     * @param string $id
     * @param string $returnUrl
     * @param LoanParametersInterface $loanParameters
     * @param CartInterface $cart
     * @param CustomerInterface $customer
     * @param string|null $notifyUrl
     * @param SellerInterface|null $seller
     * @param string|null $accountNumber
     * @param string|null $transferTitle
     */
    public function __construct(
        $id,
        $returnUrl,
        $loanParameters,
        $cart,
        $customer,
        $notifyUrl = null,
        $seller = null,
        $accountNumber = null,
        $transferTitle = null
    ) {
        $this->id = $id;
        $this->returnUrl = $returnUrl;
        $this->loanParameters = $loanParameters;
        $this->cart = $cart;
        $this->customer = $customer;
        $this->notifyUrl = $notifyUrl;
        $this->seller = $seller;
        $this->accountNumber = $accountNumber;
        $this->transferTitle = $transferTitle;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritDoc}
     */
    public function getNotifyUrl()
    {
        return $this->notifyUrl !== null ? trim(strip_tags($this->notifyUrl)) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getReturnUrl()
    {
        return trim(strip_tags($this->returnUrl));
    }

    /**
     * {@inheritDoc}
     */
    public function getLoanParameters()
    {
        return $this->loanParameters;
    }

    /**
     * {@inheritDoc}
     */
    public function getCart()
    {
        return $this->cart;
    }

    /**
     * {@inheritDoc}
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * {@inheritDoc}
     */
    public function getSeller()
    {
        return $this->seller;
    }

    /**
     * {@inheritDoc}
     */
    public function getAccountNumber()
    {
        return $this->accountNumber !== null ? trim(html_entity_decode(strip_tags($this->accountNumber))) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getTransferTitle()
    {
        return $this->transferTitle !== null ? trim(html_entity_decode(strip_tags($this->transferTitle))) : null;
    }
}
