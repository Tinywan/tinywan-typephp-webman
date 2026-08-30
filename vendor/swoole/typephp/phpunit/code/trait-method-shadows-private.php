<?php

trait PrivateShadowTrait
{
    public function execute(string $value): string
    {
        return $value;
    }
}

class PrivateMethodParent
{
    private function execute(int $value, bool $flag): int
    {
        return $value;
    }
}

class PrivateMethodChild extends PrivateMethodParent
{
    use PrivateShadowTrait;
}

function main(): void
{
    var_dump((new PrivateMethodChild())->execute('ok'));
}
