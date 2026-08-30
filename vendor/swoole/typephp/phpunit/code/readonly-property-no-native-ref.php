<?php

use native_types;

class ReadonlyPropertyNoNativeRef
{
    public readonly int $integer;
    public readonly float $floating;

    public function __construct(int $integer, float $floating)
    {
        $this->integer = $integer;
        $this->floating = $floating;
    }
}
