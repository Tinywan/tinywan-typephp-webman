<?php

#[Native]
class NativeArrayAccessNestedWrite implements ArrayAccess
{
    public function offsetExists(mixed $offset): bool { return false; }
    public function offsetGet(mixed $offset): mixed { return []; }
    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
}

function modifyNativeArrayAccessNestedValue(): void
{
    $value = new NativeArrayAccessNestedWrite();
    $value['key'][] = 1;
}
