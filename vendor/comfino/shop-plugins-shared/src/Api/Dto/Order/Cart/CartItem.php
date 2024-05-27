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

namespace Comfino\Api\Dto\Order\Cart;

class CartItem
{
    /** @var string
     * @readonly */
    public $name;
    /** @var int
     * @readonly */
    public $price;
    /** @var int
     * @readonly */
    public $quantity;
    /** @var string|null
     * @readonly */
    public $externalId;
    /** @var string|null
     * @readonly */
    public $photoUrl;
    /** @var string|null
     * @readonly */
    public $ean;
    /** @var string|null
     * @readonly */
    public $category;

    /**
     * @param string $name
     * @param int $price
     * @param int $quantity
     * @param string|null $externalId
     * @param string|null $photoUrl
     * @param string|null $ean
     * @param string|null $category
     */
    public function __construct(
        string $name,
        int $price,
        int $quantity,
        ?string $externalId,
        ?string $photoUrl,
        ?string $ean,
        ?string $category
    ) {
        $this->name = $name;
        $this->price = $price;
        $this->quantity = $quantity;
        $this->externalId = $externalId;
        $this->photoUrl = $photoUrl;
        $this->ean = $ean;
        $this->category = $category;
    }
}
