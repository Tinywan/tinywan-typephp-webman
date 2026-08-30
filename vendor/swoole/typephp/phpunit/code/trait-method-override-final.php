<?php

trait FinalOverrideTrait
{
    public function execute(): void
    {
    }
}

class FinalMethodParent
{
    final public function execute(): void
    {
    }
}

class FinalMethodChild extends FinalMethodParent
{
    use FinalOverrideTrait;
}

function main(): void
{
}
