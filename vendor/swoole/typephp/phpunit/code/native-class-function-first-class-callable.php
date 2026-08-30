<?php

#[Native]
class NativeFunctionFirstClassCallableValue {}

function nativeFunctionFirstClassCallableTarget(NativeFunctionFirstClassCallableValue $value): void {}

function invalidNativeFunctionFirstClassCallable(): void
{
    $callback = nativeFunctionFirstClassCallableTarget(...);
}
