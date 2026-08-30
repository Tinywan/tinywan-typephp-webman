<?php

#[Native]
class NativePropertyHookIndirectUnset
{
    private array $stored = [1];

    public array $items {
        get => $this->stored;
        set => $this->stored = $value;
    }
}

function invalidNativePropertyHookIndirectUnset(): void
{
    $object = new NativePropertyHookIndirectUnset();
    unset($object->items[0]);
}
