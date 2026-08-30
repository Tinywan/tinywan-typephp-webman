<?php

#[Native]
class NativeClosureReturn
{
    public int $value = 1;
}

function makeNativeReturningClosure(): Closure
{
    return static function () {
        return new NativeClosureReturn();
    };
}
