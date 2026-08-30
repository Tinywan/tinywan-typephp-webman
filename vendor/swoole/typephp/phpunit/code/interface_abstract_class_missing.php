<?php
interface ContractAbstractMissing
{
    public function run(int $id): string;
}

abstract class AbstractImplMissing implements ContractAbstractMissing
{
}

class ConcreteImplMissing extends AbstractImplMissing
{
    public function run(int $id): string
    {
        return (string) $id;
    }
}

function main() {}
