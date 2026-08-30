<?php

#[Native]
class NativePropertyHookIndirectWrite
{
    private array $stored = [];

    public array $items {
        get => $this->stored;
        set => $this->stored = $value;
    }
}

function invalidNativePropertyHookIndirectWrite(): void
{
    $object = new NativePropertyHookIndirectWrite();
    $object->items[] = 1;
}
