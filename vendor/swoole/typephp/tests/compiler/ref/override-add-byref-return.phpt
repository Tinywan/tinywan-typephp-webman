--TEST--
An override may add a by-reference return and preserve alias semantics
--FILE--
<?php

class ByRefReturnParent
{
    public function value(): int
    {
        return -1;
    }
}

final class ByRefReturnChild extends ByRefReturnParent
{
    private int $stored = 10;

    public function &value(): int
    {
        return $this->stored;
    }
}

class StaticByRefReturnParent
{
    public static function value(): int
    {
        return -1;
    }
}

final class StaticByRefReturnChild extends StaticByRefReturnParent
{
    private static int $stored = 20;

    public static function &value(): int
    {
        return self::$stored;
    }
}

function readByRefReturnParent(ByRefReturnParent $value): int
{
    return $value->value();
}

function main(): void
{
    $child = new ByRefReturnChild();
    var_dump(readByRefReturnParent($child));

    $instanceAlias =& $child->value();
    $instanceAlias = 42;
    var_dump($child->value());
    var_dump(readByRefReturnParent($child));

    $instanceCopy = $child->value();
    $instanceCopy = 99;
    var_dump($child->value());

    $staticAlias =& StaticByRefReturnChild::value();
    $staticAlias = 84;
    var_dump(StaticByRefReturnChild::value());
}
?>
--EXPECT--
int(10)
int(42)
int(42)
int(42)
int(84)
