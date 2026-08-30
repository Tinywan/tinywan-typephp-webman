<?php

class NullsafeWriteInc
{
    public int $value = 1;
}

function main(): void
{
    $object = new NullsafeWriteInc();
    $object?->value++;
}
