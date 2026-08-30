<?php

#[Native]
class NativePropertyHookCompound
{
    private int $stored = 0;

    public int $value {
        get => $this->stored;
        set => $this->stored = $value;
    }
}

function native_property_hook_compound(): void
{
    $object = new NativePropertyHookCompound();
    $object->value += 1;
}
