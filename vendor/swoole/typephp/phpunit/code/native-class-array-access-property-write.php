<?php

#[Native]
class NativeArrayAccessPropertyWrite implements ArrayAccess
{
    public function offsetExists(mixed $offset): bool { return false; }
    public function offsetGet(mixed $offset): mixed { return new stdClass(); }
    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
}

function modifyNativeArrayAccessProperty(): void
{
    $value = new NativeArrayAccessPropertyWrite();
    $value['key']->property = 1;
}
