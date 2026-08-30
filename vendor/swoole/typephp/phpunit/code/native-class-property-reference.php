<?php

#[Native]
class NativeReferencedProperty
{
    public int $value = 1;
}

function main(): void
{
    $object = new NativeReferencedProperty();
    $reference =& $object->value;
}
