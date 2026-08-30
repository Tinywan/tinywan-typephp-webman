<?php
abstract class AbstractParentMissing
{
    abstract public function run(): void;
}

class ConcreteParentMissing extends AbstractParentMissing
{
}

function main() {}
