<?php

class NullsafeWriteAssignRefLeft
{
    public int $value = 1;
}

function main(): void
{
    $object = new NullsafeWriteAssignRefLeft();
    $value = 2;
    $object?->value =& $value;
}
