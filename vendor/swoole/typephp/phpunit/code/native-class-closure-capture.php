<?php

#[Native]
class NativeClosureCapture
{
    public int $value = 1;
}

function makeNativeCapturingClosure(): Closure
{
    $native = new NativeClosureCapture();
    return static function () use ($native): int {
        return 1;
    };
}
