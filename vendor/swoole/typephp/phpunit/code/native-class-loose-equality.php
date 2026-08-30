<?php

#[Native]
class NativeEqualityValue
{
    public int $value = 1;
}

function main(): void
{
    $left = new NativeEqualityValue();
    $right = new NativeEqualityValue();
    var_dump($left == $right);
}
