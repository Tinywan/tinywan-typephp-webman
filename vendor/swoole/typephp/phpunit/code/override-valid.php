<?php

namespace OverrideValid;

use \Override as Replaces;

class ParentClass
{
    public function inherited(): string
    {
        return 'parent';
    }
}

interface Named
{
    public function name(): string;
}

class ChildClass extends ParentClass implements Named
{
    #[Replaces]
    public function inherited(): string
    {
        return 'child';
    }

    #[\Override]
    public function name(): string
    {
        return 'child';
    }
}

interface ChildNamed extends Named
{
    #[\Override]
    public function name(): string;
}

trait InheritedMethod
{
    #[\Override]
    public function inherited(): string
    {
        return 'trait';
    }
}

class TraitChild extends ParentClass
{
    use InheritedMethod;
}

class InternalInterfaceImplementation implements \Stringable
{
    #[\Override]
    public function __toString(): string
    {
        return 'typephp';
    }
}
