<?php

#[Native]
class NativeArrayAccessCompound implements ArrayAccess
{
    public function offsetExists(mixed $offset): bool { return false; }
    public function offsetGet(mixed $offset): mixed { return null; }
    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
}

function modifyNativeArrayAccessCompound(): void
{
    $value = new NativeArrayAccessCompound();
    $value['key'] += 1;
}
