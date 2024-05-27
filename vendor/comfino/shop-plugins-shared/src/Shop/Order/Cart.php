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

namespace Comfino\Shop\Order;

use Comfino\Shop\Order\Cart\CartItemInterface;

class Cart implements CartInterface
{
    /** @var CartItemInterface[] */
    private $items;
    /** @var int */
    private $totalAmount;
    /** @var int|null */
    private $deliveryCost;
    /** @var string|null */
    private $category;

    /**
     * @param CartItemInterface[] $items
     * @param int $totalAmount
     * @param int|null $deliveryCost
     * @param string|null $category
     */
    public function __construct(array $items, int $totalAmount, ?int $deliveryCost = null, ?string $category = null)
    {
        $this->items = $items;
        $this->totalAmount = $totalAmount;
        $this->deliveryCost = $deliveryCost;
        $this->category = $category;
    }

    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @inheritDoc
     */
    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    /**
     * @inheritDoc
     */
    public function getDeliveryCost(): ?int
    {
        return $this->deliveryCost;
    }

    /**
     * @inheritDoc
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }
}
