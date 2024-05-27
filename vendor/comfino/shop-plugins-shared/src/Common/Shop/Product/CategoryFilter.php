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

namespace Comfino\Common\Shop\Product;

use Comfino\Common\Shop\Cart;

class CategoryFilter
{
    /**
     * @readonly
     * @var \Comfino\Common\Shop\Product\CategoryTree
     */
    private $categoryTree;
    public function __construct(CategoryTree $categoryTree)
    {
        $this->categoryTree = $categoryTree;
    }

    /**
     * @param int[] $excludedCategoryIds
     * @param int $categoryId
     */
    public function isCategoryAvailable($categoryId, $excludedCategoryIds): bool
    {
        if (in_array($categoryId, $excludedCategoryIds, true)) {
            return false;
        }

        if (($categoryNode = $this->categoryTree->getNodeById($categoryId)) === null) {
            return false;
        }

        foreach ($excludedCategoryIds as $excludedCategoryId) {
            if (($excludedCategory = $this->categoryTree->getNodeById($excludedCategoryId)) === null) {
                continue;
            }

            if ($categoryNode->isDescendantOf($excludedCategory)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param int[] $excludedCategoryIds
     * @param \Comfino\Common\Shop\Cart $cart
     */
    public function isCartValid($cart, $excludedCategoryIds): bool
    {
        if (empty($excludedCategoryIds || empty($cart->getCartItems()))) {
            return true;
        }

        $cartCategoryIds = $cart->getCartCategoryIds();

        if (count(array_intersect($cartCategoryIds, $excludedCategoryIds))) {
            return false;
        }

        foreach ($cartCategoryIds as $categoryId) {
            if (!$this->isCategoryAvailable($categoryId, $excludedCategoryIds)) {
                return false;
            }
        }

        return true;
    }
}
