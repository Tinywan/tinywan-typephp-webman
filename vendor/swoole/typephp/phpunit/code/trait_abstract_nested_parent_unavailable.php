<?php
class NestedParentBase {}

trait NestedParentRequirement
{
    abstract public function copy(parent $value): parent;
}

trait NestedParentImplementation
{
    public function copy(NestedParentBase $value): NestedParentBase
    {
        return $value;
    }
}

trait InvalidNestedParentComposition
{
    use NestedParentRequirement, NestedParentImplementation;
}

class NestedParentConsumer extends NestedParentBase
{
    use InvalidNestedParentComposition;
}

function main() {}
