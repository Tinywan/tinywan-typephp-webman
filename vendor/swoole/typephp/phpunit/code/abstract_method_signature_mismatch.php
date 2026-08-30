<?php
abstract class AbstractSignatureBase
{
    abstract public function run(int $id): string;
}

class AbstractSignatureChild extends AbstractSignatureBase
{
    public function run(string $id): string
    {
        return $id;
    }
}

function main() {}
