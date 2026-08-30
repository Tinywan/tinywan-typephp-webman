<?php

class NullsafeWriteAssignRefRight
{
    public int $value = 1;
}

function main(): void
{
    $object = new NullsafeWriteAssignRefRight();
    $value =& $object?->value;
}
