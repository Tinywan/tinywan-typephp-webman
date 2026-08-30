--TEST--
Keyword MethodsFor method with snake_case name
--FILE--
<?php
declare(strict_types=1);
use native_types;

#[MethodsFor('*')]
final class KeywordExtensions
{
    public static function var_dump(mixed $var): void
    {
        var_dump($var);
    }
}

function main(): void {
    $str = "hello world";
    $str->var_dump();

    $int_val = 42;
    $int_val->var_dump();

    $float_val = 3.14;
    $float_val->var_dump();
}
?>
--EXPECT--
string(11) "hello world"
int(42)
float(3.14)
