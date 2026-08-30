<?php

class ReadonlyCloneClosure
{
    public readonly int $value;

    public function __construct()
    {
        $this->value = 1;
    }

    public function __clone(): void
    {
        $write = function (): void {
            $this->value = 2;
        };
        $write();
    }
}
