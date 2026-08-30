<?php

class NullsafeWriteAssign
{
    public int $value = 1;
}

function main(): void
{
    $object = new NullsafeWriteAssign();
    $object?->value = 2;
}
