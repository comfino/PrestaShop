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

namespace Comfino\Product\CategoryTree;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'comfino/src/Product/CategoryTree/NodeIterator.php';

use Comfino\Product\CategoryTree\NodeIterator;

final class Node
{
    /**
     * @readonly
     *
     * @var int
     */
    private $id;

    /**
     * @readonly
     *
     * @var string
     */
    private $name;

    /**
     * @var \Comfino\Product\CategoryTree\Node|null
     */
    private $parent;

    /**
     * @var \Comfino\Product\CategoryTree\NodeIterator|null
     */
    private $children;

    /**
     * @param int $id
     * @param string $name
     * @param Node|null $parent
     * @param NodeIterator|null $children
     */
    public function __construct($id, $name, $parent = null, $children = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->parent = $parent;
        $this->children = $children;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Node|null
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param Node|null $parent
     *
     * @return void
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    /**
     * @return NodeIterator|null
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param NodeIterator $children
     *
     * @return void
     */
    public function setChildren(NodeIterator $children)
    {
        $this->children = $children;
    }

    /**
     * @return bool
     */
    public function isRoot()
    {
        return $this->parent === null;
    }

    /**
     * @return bool
     */
    public function isLeaf()
    {
        return !$this->hasChildren();
    }

    /**
     * @param Node $node
     *
     * @return bool
     */
    public function isParentOf(Node $node)
    {
        return $this === $node->getParent();
    }

    /**
     * @param Node $node
     *
     * @return bool
     */
    public function isChildOf(Node $node)
    {
        return $node === $this->parent;
    }

    /**
     * @param Node $node
     *
     * @return bool
     */
    public function isAncestorOf(Node $node)
    {
        if ($this->isParentOf($node)) {
            return true;
        }

        if ($this->isLeaf()) {
            return false;
        }

        foreach ($this->children as $childNode) {
            if ($childNode->isAncestorOf($node)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Node $node
     *
     * @return bool
     */
    public function isDescendantOf(Node $node)
    {
        if ($this->isChildOf($node)) {
            return true;
        }

        if ($this->isRoot()) {
            return false;
        }

        $parentNode = $this->parent;

        while ($parentNode !== null) {
            if ($parentNode === $node) {
                return true;
            }

            $parentNode = $parentNode->getParent();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        return $this->children !== null && $this->children->count() !== 0;
    }

    /**
     * @return NodeIterator
     */
    public function getPathToRoot()
    {
        $nodes = [];
        $node = $this;

        do {
            $nodes[] = $node;
        } while (($node = $node->getParent()) !== null);

        return new NodeIterator($nodes);
    }
}
