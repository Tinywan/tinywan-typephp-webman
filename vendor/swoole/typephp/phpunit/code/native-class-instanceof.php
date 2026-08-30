<?php

#[Native]
class NativeInstanceofTarget
{
    public int $value = 0;
}

function testNativeInstanceof(string $class): bool
{
    $value = new NativeInstanceofTarget();
    return $value instanceof $class;
}
