<?php

#[Native]
class NativePropertyHookIsset
{
    public int $value {
        get => 1;
    }
}

function native_property_hook_isset(): void
{
    $object = new NativePropertyHookIsset();
    isset($object->value);
}
