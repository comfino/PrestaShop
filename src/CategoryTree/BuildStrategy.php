<?php

namespace Comfino\CategoryTree;

use Comfino\Common\Shop\Product\CategoryTree\BuildStrategyInterface;
use Comfino\Common\Shop\Product\CategoryTree\Descriptor;
use Comfino\Common\Shop\Product\CategoryTree\Node;
use Comfino\Common\Shop\Product\CategoryTree\NodeIterator;

class BuildStrategy implements BuildStrategyInterface
{
    /** @var Descriptor */
    private $descriptor;

    public function build(): Descriptor
    {
        if ($this->descriptor !== null) {
            return $this->descriptor;
        }

        $this->descriptor = new Descriptor();
        $this->descriptor->index = [];

        $nodes = [];

        foreach (\Category::getNestedCategories() as $category) {
            $node = new Node($category['id_category'], $category['name']);

            if (!empty($category['children'])) {
                $childNodes = [];

                foreach ($category['children'] as $childCategory) {
                    $childNodes[] = $this->processCategory($node, $childCategory);
                }

                $node->setChildren(new NodeIterator($childNodes));
            }

            $nodes[] = $node;
            $this->descriptor->index[$node->getId()] = $node;
        }

        $this->descriptor->nodes = new NodeIterator($nodes);

        return $this->descriptor;
    }

    private function processCategory(Node $parentNode, array $category): Node
    {
        $node = new Node($category['id_category'], $category['name'], $parentNode);

        if (!empty($category['children'])) {
            $childNodes = [];

            foreach ($category['children'] as $childCategory) {
                $childNodes[] = $this->processCategory($node, $childCategory);
            }

            $node->setChildren(new NodeIterator($childNodes));
        }

        $this->descriptor->index[$node->getId()] = $node;

        return $node;
    }
}
