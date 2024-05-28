<?php

namespace Comfino;

trait ReflectionTrait
{
    public function getConstantFromObject(object $object, string $name): mixed
    {
        return (new \ReflectionObject($object))->getConstant($name);
    }

    /**
     * @throws \ReflectionException
     */
    public function getConstantFromClass(string $class, string $name): mixed
    {
        return (new \ReflectionClass($class))->getConstant($name);
    }
}
