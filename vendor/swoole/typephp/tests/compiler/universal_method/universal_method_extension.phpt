--TEST--
Universal methods provided by MethodsFor classes
--FILE--
<?php

use native_types;

#[MethodsFor(Type::Int)]
final class IntExtensions
{
    public static function to_bytes(int $int, string $unit = 'Kb'): string
    {
        return ($int / 1024) . $unit;
    }
}

#[MethodsFor(Type::Array)]
final class ArrayExtensions
{
    public static function get_first_element(array $array): mixed
    {
        return $array[0];
    }
}

#[MethodsFor(Type::String)]
final class StringExtensions
{
    public static function shout(string $str): string
    {
        return strtoupper($str) . '!';
    }
}

function main()
{
    $num = 1024 * 512;
    var_dump($num->to_bytes());

    $array = [22, 33, 44];
    var_dump($array->get_first_element());

    $str = "hello";
    var_dump($str->shout());
}
?>
--EXPECT--
string(5) "512Kb"
int(22)
string(6) "HELLO!"
