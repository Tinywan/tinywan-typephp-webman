<?php
interface ContractAbstractMismatch
{
    public function run(int $id): string;
}

abstract class AbstractImplMismatch implements ContractAbstractMismatch
{
    abstract public function run(string $id): string;
}

function main() {}
