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

namespace Comfino\Common\Backend\Payment\ProductTypeFilter;

use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Shop\Cart;
use Comfino\Common\Shop\Product\CategoryFilter;

class FilterByExcludedCategory implements ProductTypeFilterInterface
{
    /**
     * @readonly
     * @var \Comfino\Common\Shop\Product\CategoryFilter
     */
    private $categoryFilter;
    /**
     * @var int[][]
     * @readonly
     */
    private $excludedCategoryIdsByProductType;
    /**
     * @param int[][] $excludedCategoryIdsByProductType ['PRODUCT_TYPE' => [excluded_category_ids]]
     */
    public function __construct(CategoryFilter $categoryFilter, array $excludedCategoryIdsByProductType)
    {
        $this->categoryFilter = $categoryFilter;
        $this->excludedCategoryIdsByProductType = $excludedCategoryIdsByProductType;
    }

    /**
     * @param mixed[] $availableProductTypes
     * @param \Comfino\Common\Shop\Cart $cart
     */
    public function getAllowedProductTypes($availableProductTypes, $cart): array
    {
        $allowedProductTypes = [];

        foreach ($availableProductTypes as $productType) {
            if (array_key_exists((string) $productType, $this->excludedCategoryIdsByProductType)) {
                if ($this->categoryFilter->isCartValid($cart, $this->excludedCategoryIdsByProductType[(string) $productType])) {
                    $allowedProductTypes[] = $productType;
                }
            } else {
                $allowedProductTypes[] = $productType;
            }
        }

        return $allowedProductTypes;
    }
}
