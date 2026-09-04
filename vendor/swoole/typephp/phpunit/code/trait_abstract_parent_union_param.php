<?php
class ParentParamBase {}

trait ParentParamRequirement
{
    abstract public function accept(parent|null $value): void;
}

class InvalidParentParamImplementation extends ParentParamBase
{
    use ParentParamRequirement;

    public function accept(stdClass|null $value): void {}
}

function main() {}
