<?php

class ReadonlyPropertyUnset
{
    public readonly int $value;

    public function __construct()
    {
        $this->value = 1;
    }
}

function main(): void
{
    $object = new ReadonlyPropertyUnset();
    unset($object->value);
}
