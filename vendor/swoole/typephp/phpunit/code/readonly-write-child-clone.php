<?php

class ReadonlyCloneParent
{
    public readonly int $value;

    public function __construct()
    {
        $this->value = 1;
    }
}

class ReadonlyCloneChild extends ReadonlyCloneParent
{
    public function __clone(): void
    {
        $this->value = 2;
    }
}
