<?php

class NullsafeWriteAssignOp
{
    public int $value = 1;
}

function main(): void
{
    $object = new NullsafeWriteAssignOp();
    $object?->value += 2;
}
