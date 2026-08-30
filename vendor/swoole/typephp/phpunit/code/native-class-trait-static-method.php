<?php

trait NativeStaticMethodTrait
{
    public static function value(): int
    {
        return 1;
    }
}

#[Native]
class NativeTraitStaticMethod
{
    use NativeStaticMethodTrait;
}
