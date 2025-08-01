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

namespace Comfino\Order\Cart;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Order/Cart/ProductInterface.php';

use Comfino\Order\Cart\ProductInterface;

class Product implements ProductInterface
{
    /**
     * @var string
     *
     * @readonly
     */
    private $name;

    /**
     * @var int
     *
     * @readonly
     */
    private $price;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $id;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $category;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $ean;

    /**
     * @var string|null
     *
     * @readonly
     */
    private $photoUrl;

    /**
     * @var int[]|null
     *
     * @readonly
     */
    private $categoryIds;

    /**
     * @var int|null
     *
     * @readonly
     */
    private $netPrice;

    /**
     * @var int|null
     *
     * @readonly
     */
    private $taxRate;

    /**
     * @var int|null
     *
     * @readonly
     */
    private $taxValue;

    /**
     * @param string $name
     * @param int $price
     * @param string|null $id
     * @param string|null $category
     * @param string|null $ean
     * @param string|null $photoUrl
     * @param int[]|null $categoryIds
     * @param int|null $netPrice
     * @param int|null $taxRate
     * @param int|null $taxValue
     */
    public function __construct(
        $name,
        $price,
        $id = null,
        $category = null,
        $ean = null,
        $photoUrl = null,
        $categoryIds = null,
        $netPrice = null,
        $taxRate = null,
        $taxValue = null
    ) {
        $this->name = $name;
        $this->price = $price;
        $this->id = $id;
        $this->category = $category;
        $this->ean = $ean;
        $this->photoUrl = $photoUrl;
        $this->categoryIds = $categoryIds;
        $this->netPrice = $netPrice;
        $this->taxRate = $taxRate;
        $this->taxValue = $taxValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return trim(html_entity_decode(strip_tags($this->name)));
    }

    /**
     * {@inheritDoc}
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * {@inheritDoc}
     */
    public function getNetPrice()
    {
        return $this->netPrice;
    }

    /**
     * {@inheritDoc}
     */
    public function getTaxRate()
    {
        return $this->taxRate;
    }

    /**
     * {@inheritDoc}
     */
    public function getTaxValue()
    {
        return $this->taxValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return $this->id !== null ? trim(strip_tags($this->id)) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getCategory()
    {
        return $this->category !== null ? trim(strip_tags($this->category)) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getEan()
    {
        return $this->ean !== null ? trim(strip_tags($this->ean)) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getPhotoUrl()
    {
        return $this->photoUrl !== null ? trim(strip_tags($this->photoUrl)) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getCategoryIds()
    {
        return $this->categoryIds;
    }
}
