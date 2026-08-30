<?php

#[Native]
class NativeDynamicStaticMagicMethod
{
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return null;
    }
}
