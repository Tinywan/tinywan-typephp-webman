<?php

#[Native]
class NativePropertyHookIndirectReference
{
    private array $stored = [1];

    public array $items {
        get => $this->stored;
        set => $this->stored = $value;
    }
}

function invalidNativePropertyHookIndirectReference(): void
{
    $object = new NativePropertyHookIndirectReference();
    $reference =& $object->items[0];
}
