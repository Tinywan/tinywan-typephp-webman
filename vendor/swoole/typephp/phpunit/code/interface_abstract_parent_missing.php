<?php
interface ContractAbstractParentMissing
{
    public function run(): void;
}

abstract class AbstractContractParentMissing implements ContractAbstractParentMissing
{
}

class ConcreteContractParentMissing extends AbstractContractParentMissing
{
}

function main() {}
