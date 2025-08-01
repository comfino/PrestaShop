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

namespace Comfino\Api;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Order/Cart/CartItemInterface.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Api/CartInterface.php';

use Comfino\Api\CartInterface;
use Comfino\Order\Cart\CartItemInterface;

class Cart implements CartInterface
{
    /**
     * @var CartItemInterface[]
     *
     * @readonly
     */
    private $items;

    /**
     * @var int
     *
     * @readonly
     */
    private $totalAmount;

    /**
     * @var int|null
     *
     * @readonly
     */
    private $deliveryCost;

    /**
     * @var int|null
     *
     * @readonly
     */
    private $deliveryNetCost;

    /**
     * @var int|null
     *
     * @readonly
     */
    private $deliveryCostTaxRate;

    /**
     * @var int|null
     *
     * @readonly
     */
    private $deliveryCostTaxValue;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $category;

    /**
     * @param CartItemInterface[] $items
     * @param int $totalAmount
     * @param int|null $deliveryCost
     * @param int|null $deliveryNetCost
     * @param int|null $deliveryCostTaxRate
     * @param int|null $deliveryCostTaxValue
     * @param string|null $category
     */
    public function __construct(
        array $items,
        $totalAmount,
        $deliveryCost = null,
        $deliveryNetCost = null,
        $deliveryCostTaxRate = null,
        $deliveryCostTaxValue = null,
        $category = null
    ) {
        $this->items = $items;
        $this->totalAmount = $totalAmount;
        $this->deliveryCost = $deliveryCost;
        $this->deliveryNetCost = $deliveryNetCost;
        $this->deliveryCostTaxRate = $deliveryCostTaxRate;
        $this->deliveryCostTaxValue = $deliveryCostTaxValue;
        $this->category = $category;
    }

    /**
     * {@inheritDoc}
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * {@inheritDoc}
     */
    public function getTotalAmount()
    {
        return $this->totalAmount;
    }

    /**
     * {@inheritDoc}
     */
    public function getDeliveryCost()
    {
        return $this->deliveryCost;
    }

    public function getDeliveryNetCost()
    {
        return $this->deliveryNetCost;
    }

    public function getDeliveryCostTaxRate()
    {
        return $this->deliveryCostTaxRate;
    }

    public function getDeliveryCostTaxValue()
    {
        return $this->deliveryCostTaxValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getCategory()
    {
        return $this->category;
    }
}
