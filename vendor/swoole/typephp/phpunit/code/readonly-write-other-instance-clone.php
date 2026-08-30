<?php

class ReadonlyCloneOtherInstance
{
    public readonly int $value;

    public function __construct()
    {
        $this->value = 1;
    }

    public function __clone(): void
    {
        $other = new self();
        $other->value = 2;
    }
}
