<?php
interface ContractTrait
{
    public function run(int $id): string;
}

trait ContractTraitImpl
{
    public function run(int $id): string
    {
        return (string) $id;
    }
}

class ImplTrait implements ContractTrait
{
    use ContractTraitImpl;
}

function main() {}
