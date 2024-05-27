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

use Comfino\Common\Shop\Product\CategoryTree\BuildStrategyInterface;
use Comfino\Common\Shop\Product\CategoryTree\Node;
use Comfino\Common\Shop\Product\CategoryTree\NodeIterator;

final class CategoryTree
{
    /**
     * @readonly
     * @var \Comfino\Common\Shop\Product\CategoryTree\BuildStrategyInterface
     */
    private $buildStrategy;
    /**
     * @var \Comfino\Common\Shop\Product\CategoryTree\NodeIterator|null
     */
    private $nodes;

    /** @var Node[]|null */
    private $index;

    public function __construct(BuildStrategyInterface $buildStrategy)
    {
        $this->buildStrategy = $buildStrategy;
    }

    public function getNodes(): NodeIterator
    {
        if ($this->nodes === null) {
            $treeDescriptor = $this->buildStrategy->build();

            $this->nodes = $treeDescriptor->nodes;
            $this->index = $treeDescriptor->index;
        }

        return $this->nodes;
    }

    /**
     * @return int[]
     */
    public function getNodeIds(?Node $rootNode = null): array
    {
        if (!count($this->getNodes())) {
            return [];
        }

        if ($this->index !== null) {
            return array_keys($this->index);
        }

        if ($rootNode === null) {
            $nodeIds = array_map(static function (Node $node) : int {
                return $node->getId();
            }, iterator_to_array($this->nodes));
            $subNodeIds = [];

            foreach ($this->nodes as $node) {
                if ($node->hasChildren()) {
                    $subNodeIds[] = $this->getNodeIds($node);
                }
            }

            $nodeIds = array_merge($nodeIds, ...$subNodeIds);
        } else {
            $nodeIds = [$rootNode->getId()];

            if ($rootNode->hasChildren()) {
                $subNodeIds = [];

                foreach ($rootNode->getChildren() as $node) {
                    $subNodeIds[] = $this->getNodeIds($node);
                }

                $nodeIds = array_merge($nodeIds, ...$subNodeIds);
            }
        }

        return $nodeIds;
    }

    public function getNodeById(int $id, ?Node $rootNode = null): ?Node
    {
        if ($this->index !== null && array_key_exists($id, $this->index)) {
            return $this->index[$id];
        }

        if ($this->index === null) {
            $this->index = [];
        }

        if ($rootNode === null) {
            foreach ($this->getNodes() as $node) {
                $this->index[$node->getId()] = $node;

                if ($node->getId() === $id) {
                    return $node;
                }
            }

            foreach ($this->getNodes() as $node) {
                if ($node->hasChildren()) {
                    foreach ($node->getChildren() as $childNode) {
                        $this->index[$childNode->getId()] = $childNode;

                        if ($childNode->getId() === $id) {
                            return $childNode;
                        }
                    }

                    foreach ($node->getChildren() as $childNode) {
                        if (($foundNode = $this->getNodeById($id, $childNode)) !== null) {
                            return $foundNode;
                        }
                    }
                }
            }
        } else {
            $this->index[$rootNode->getId()] = $rootNode;

            if ($rootNode->getId() === $id) {
                return $rootNode;
            }

            if ($rootNode->hasChildren()) {
                foreach ($rootNode->getChildren() as $childNode) {
                    $this->index[$childNode->getId()] = $childNode;

                    if ($childNode->getId() === $id) {
                        return $childNode;
                    }
                }

                foreach ($rootNode->getChildren() as $childNode) {
                    if (($foundNode = $this->getNodeById($id, $childNode)) !== null) {
                        return $foundNode;
                    }
                }
            }
        }

        $this->index[$id] = null;

        return null;
    }
}
