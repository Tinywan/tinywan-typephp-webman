<?php
interface ContractAbstractMethod
{
    public function run(int $id): string;
}

abstract class AbstractImplMethod implements ContractAbstractMethod
{
    abstract public function run(int $id): string;
}

class ConcreteImplMethod extends AbstractImplMethod
{
    public function run(int $id): string
    {
        return (string) $id;
    }
}

function main() {}
