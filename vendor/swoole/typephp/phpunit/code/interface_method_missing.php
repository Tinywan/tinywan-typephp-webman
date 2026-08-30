<?php
interface ContractMissing
{
    public function run(int $id): void;
}

class ImplMissing implements ContractMissing
{
}

function main() {}
