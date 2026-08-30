--TEST--
MethodsFor method chaining with typed returns
--FILE--
<?php

use native_types;

#[MethodsFor(Type::Int)]
final class IntExtensions
{
    public static function to_words(int $int): string
    {
        $map = [1 => 'one', 2 => 'two', 3 => 'three'];
        return $map[$int] ?? 'unknown';
    }
}

#[MethodsFor(Type::String)]
final class StringExtensions
{
    public static function double(string $str): string
    {
        return $str . $str;
    }

    public static function get_length(string $str): int
    {
        return strlen($str);
    }

    public static function to_array(string $str, string $delimiter): array
    {
        return $str->split($delimiter);
    }
}

#[MethodsFor(Type::Array)]
final class ArrayExtensions
{
    public static function last(array $arr): mixed
    {
        if ($arr->count() === 0) {
            return null;
        }
        return $arr[$arr->count() - 1];
    }
}

function main()
{
    $num = 2;
    var_dump($num->to_words()->upper());
    var_dump($num->to_words()->double()->upper());

    $str = "hello";
    var_dump($str->get_length()->add(100));
    var_dump($str->double()->length());

    $str2 = "hello world";
    var_dump($str2->split(" ")->last());
}
?>
--EXPECT--
string(3) "TWO"
string(6) "TWOTWO"
int(105)
int(10)
string(5) "world"
