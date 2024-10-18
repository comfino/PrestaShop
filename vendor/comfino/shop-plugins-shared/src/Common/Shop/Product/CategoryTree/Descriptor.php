<?php

namespace Comfino\Common\Shop\Product\CategoryTree;

final readonly class Descriptor
{
    /**
     * @param Node[]|null $index
     */
    public function __construct(public NodeIterator $nodes, public ?array $index)
    {
    }
}
