--TEST--
Universal method provider may wrap a PHP internal function
--FILE--
<?php

use native_types;

#[MethodsFor(Type::String)]
final class StringExtensions
{
    public static function rot13(string $value): string
    {
        return str_rot13($value);
    }
}

function main()
{
    $str = "hello";
    var_dump($str->rot13());
    var_dump($str->rot13()->upper());
    var_dump($str->rot13()->length());
}
?>
--EXPECT--
string(5) "uryyb"
string(5) "URYYB"
int(5)
