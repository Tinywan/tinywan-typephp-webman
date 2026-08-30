<?php

#[Native]
class NativeFirstClassCallable
{
    public function run(): void {}
}

function invalidNativeFirstClassCallable(): void
{
    $object = new NativeFirstClassCallable();
    $callback = $object->run(...);
}
