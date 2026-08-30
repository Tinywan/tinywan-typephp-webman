<?php
interface ContractMismatch
{
    public function run(int $id): string;
}

class ImplMismatch implements ContractMismatch
{
    public function run(string $id): string
    {
        return $id;
    }
}

function main() {}
