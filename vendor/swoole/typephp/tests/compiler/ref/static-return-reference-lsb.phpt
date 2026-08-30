--TEST--
Reference-returning static method forwards a late-static-bound reference
--FILE--
<?php
class RefLsbBase
{
    protected static int $value = 1;

    protected static function &valueRef(): mixed
    {
        return static::$value;
    }

    public static function &forward(): mixed
    {
        return static::valueRef();
    }

    public static function value(): int
    {
        return static::$value;
    }
}

class RefLsbChild extends RefLsbBase
{
    protected static int $value = 2;
}

function main(): void
{
    $value = &RefLsbChild::forward();
    $value = 20;
    var_dump(RefLsbChild::value(), RefLsbBase::value());
}
?>
--EXPECT--
int(20)
int(1)
