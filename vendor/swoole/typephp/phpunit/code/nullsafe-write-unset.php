<?php

class NullsafeWriteUnset
{
    public int $value = 1;
}

function main(): void
{
    $object = new NullsafeWriteUnset();
    unset($object?->value);
}
