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

namespace Comfino\Product;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Product/CategoryTree/Descriptor.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Product/CategoryTree/Node.php';
require_once _PS_MODULE_DIR_ . 'comfino/src/Product/CategoryTree/NodeIterator.php';

use Comfino\Product\CategoryTree\Descriptor;
use Comfino\Product\CategoryTree\Node;
use Comfino\Product\CategoryTree\NodeIterator;

class CategoryManager
{
    /**
     * @param Category[] $nestedCategories
     *
     * @return Descriptor
     */
    public static function buildCategoryTree($nestedCategories)
    {
        $nodes = [];
        $index = [];

        foreach ($nestedCategories as $category) {
            $node = new Node($category->id, $category->name);

            if (!empty($category->children)) {
                $childNodes = [];

                foreach ($category->children as $childCategory) {
                    $childNodes[] = self::processCategory($node, $childCategory, $index);
                }

                $node->setChildren(new NodeIterator($childNodes));
            }

            $nodes[] = $node;
            $index[$node->getId()] = $node;
        }

        return new Descriptor(new NodeIterator($nodes), $index);
    }

    /**
     * @param Node $parentNode
     * @param Category $category
     * @param array $index
     *
     * @return Node
     */
    private static function processCategory(Node $parentNode, Category $category, array &$index)
    {
        $node = new Node($category->id, $category->name, $parentNode);

        if (!empty($category->children)) {
            $childNodes = [];

            foreach ($category->children as $childCategory) {
                $childNodes[] = self::processCategory($node, $childCategory, $index);
            }

            $node->setChildren(new NodeIterator($childNodes));
        }

        $index[$node->getId()] = $node;

        return $node;
    }
}
