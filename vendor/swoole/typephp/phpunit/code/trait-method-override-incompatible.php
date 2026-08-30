<?php

trait IncompatibleTrait
{
    public function test(int $value)
    {
    }
}

class IncompatibleParent
{
    protected function test(int $value, bool $bool)
    {
    }
}

class IncompatibleChild extends IncompatibleParent
{
    use IncompatibleTrait;
}

function main(): void
{
}
