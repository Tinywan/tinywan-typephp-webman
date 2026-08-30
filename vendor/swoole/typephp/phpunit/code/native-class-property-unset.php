<?php

#[Native]
class NativeUnsetProperty
{
    public int $value = 1;
}

function main(): void
{
    $object = new NativeUnsetProperty();
    unset($object->value);
}
