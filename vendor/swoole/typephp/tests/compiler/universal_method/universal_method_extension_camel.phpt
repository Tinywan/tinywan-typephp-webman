--TEST--
Universal MethodsFor methods use their declared names
--FILE--
<?php

use native_types;

#[MethodsFor(Type::Int)]
final class IntExtensions
{
    public static function toBytes(int $value): string
    {
        return ($value / 1024) . 'Kb';
    }
}

#[MethodsFor(Type::Array)]
final class ArrayExtensions
{
    public static function getFirstElement(array $value): mixed
    {
        return $value[0];
    }
}

function main(): void
{
    $size = 2048;
    $values = [42, 43];
    var_dump($size->toBytes());
    var_dump($values->getFirstElement());
}
?>
--EXPECT--
string(3) "2Kb"
int(42)
