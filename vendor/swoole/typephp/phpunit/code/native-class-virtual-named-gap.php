<?php

#[Native]
class NativeNamedGapBase
{
    public function value(int $first = 1, int $second = 10): int
    {
        return $first + $second;
    }
}

#[Native]
class NativeNamedGapChild extends NativeNamedGapBase
{
    public function value(int $first = 2, int $second = 20): int
    {
        return $first + $second;
    }
}

function callNativeNamedGap(NativeNamedGapBase $value): int
{
    return $value->value(second: 50);
}

