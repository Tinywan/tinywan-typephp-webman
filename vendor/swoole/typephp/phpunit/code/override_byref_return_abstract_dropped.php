<?php

abstract class AbstractByRefReturn
{
    abstract public function &value(): array;
}

class ConcreteByRefReturn extends AbstractByRefReturn
{
    public function value(): array
    {
        return [];
    }
}

function main() {}
