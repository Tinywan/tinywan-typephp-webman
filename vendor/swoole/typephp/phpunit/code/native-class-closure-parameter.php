<?php

#[Native]
class NativeClosureParameter
{
    public int $value = 1;
}

function makeNativeParameterClosure(): Closure
{
    return static function (NativeClosureParameter $native): int {
        return $native->value;
    };
}
