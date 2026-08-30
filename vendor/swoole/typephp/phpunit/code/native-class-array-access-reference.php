<?php

#[Native]
class NativeArrayAccessReference implements ArrayAccess
{
    public function offsetExists(mixed $offset): bool { return false; }
    public function offsetGet(mixed $offset): mixed { return null; }
    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
}

function referenceNativeArrayAccessValue(): void
{
    $value = new NativeArrayAccessReference();
    $reference = &$value['key'];
}
