<?php

namespace Comfino\Common\Shop\Product\CategoryTree;

final readonly class Descriptor
{
    public NodeIterator $nodes;

    /** @var Node[]|null */
    public ?array $index;
}
