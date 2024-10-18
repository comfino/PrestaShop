<?php

namespace Comfino\Common\Shop\Product\CategoryTree;

class NodeIterator implements \Iterator, \Countable
{
    /**
     * @param Node[] $nodes
     */
    public function __construct(private array $nodes)
    {
    }

    public function current(): Node
    {
        return current($this->nodes);
    }

    public function next(): void
    {
        next($this->nodes);
    }

    public function key(): int
    {
        return key($this->nodes);
    }

    public function valid(): bool
    {
        return key($this->nodes) !== null;
    }

    public function rewind(): void
    {
        reset($this->nodes);
    }

    public function count(): int
    {
        return count($this->nodes);
    }
}
