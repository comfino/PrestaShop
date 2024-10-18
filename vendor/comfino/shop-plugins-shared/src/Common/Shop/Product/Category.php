<?php

namespace Comfino\Common\Shop\Product;

final readonly class Category
{
    /**
     * @param Category[] $children
     */
    public function __construct(public int $id, public string $name, public int $position, public array $children)
    {
    }
}
