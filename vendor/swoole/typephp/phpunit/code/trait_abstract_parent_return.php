<?php
class ParentReturnBase {}

trait ParentReturnRequirement
{
    abstract public function make(): parent;
}

class InvalidParentReturnImplementation extends ParentReturnBase
{
    use ParentReturnRequirement;

    public function make(): stdClass
    {
        return new stdClass();
    }
}

function main() {}
