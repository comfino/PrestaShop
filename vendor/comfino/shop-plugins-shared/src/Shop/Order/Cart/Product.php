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

namespace Comfino\Shop\Order\Cart;

class Product implements ProductInterface
{
    /** @var string */
    private $name;
    /** @var int */
    private $price;
    /** @var string|null */
    private $id;
    /** @var string|null */
    private $category;
    /** @var string|null */
    private $ean;
    /** @var string|null */
    private $photoUrl;
    /** @var int[]|null */
    private $categoryIds;

    /**
     * @param string $name
     * @param int $price
     * @param string|null $id
     * @param string|null $category
     * @param string|null $ean
     * @param string|null $photoUrl
     * @param int[]|null $categoryIds
     */
    public function __construct(
        string $name,
        int $price,
        ?string $id = null,
        ?string $category = null,
        ?string $ean = null,
        ?string $photoUrl = null,
        ?array $categoryIds = null
    ) {
        $this->name = $name;
        $this->price = $price;
        $this->id = $id;
        $this->category = $category;
        $this->ean = $ean;
        $this->photoUrl = $photoUrl;
        $this->categoryIds = $categoryIds;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * @inheritDoc
     */
    public function getEan(): ?string
    {
        return $this->ean;
    }

    /**
     * @inheritDoc
     */
    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    /**
     * @inheritDoc
     */
    public function getCategoryIds(): ?array
    {
        return $this->categoryIds;
    }
}
