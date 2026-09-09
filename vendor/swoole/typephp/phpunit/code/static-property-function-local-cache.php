<?php

class StaticPropertyFunctionLocalCache
{
    public static mixed $value = 1;

    public static function noStaticProperty(): int
    {
        return 1;
    }

    public static function repeatedSelf(): array
    {
        self::$value = null;
        return [self::$value, self::$value];
    }

    public static function repeatedStatic(): array
    {
        static::$value = null;
        return [static::$value, static::$value, static::class, static::class];
    }
}

function main(): void
{
}
