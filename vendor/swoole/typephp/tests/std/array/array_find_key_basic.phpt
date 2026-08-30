--TEST--
Test array_find_key() function : basic functionality
--SKIPIF--
<?php //die("skip AOT does not support string-based callbacks"); ?>

--FILE--
<?php
function even($input, $key)
{
    return $input % 2 === 0;
}
class EvenClass
{
    public static function even($input, $key)
    {
        return $input % 2 === 0;
    }
}

function return_nothing($value, $key)
{
    // return nothing
}

function main()
{
    $array1 = ["a" => 1, "b" => 2, "c" => 3, "d" => 4, "e" => 5];
    $array2 = [1, 2, 3, 4, 5];
    var_dump(array_find_key($array1, fn($value, $key) => $value > 3));
    var_dump(array_find_key($array2, fn($value, $key) => $value > 3));
    var_dump(array_find_key($array2, fn($value, $key) => $value > 5));
    var_dump(array_find_key([], fn($value, $key) => true));
    var_dump(array_find_key($array1, fn($value, $key) => $key === "c"));
    var_dump(array_find_key($array1, fn($value, $key) => false));
    var_dump(array_find_key($array1, 'even'));
    var_dump(array_find_key($array1, 'return_nothing'));
    var_dump(array_find_key($array1, ['EvenClass', 'even']));
    var_dump(array_find_key($array1, "EvenClass::even"));
}
?>
--EXPECT--
string(1) "d"
int(3)
NULL
NULL
string(1) "c"
NULL
string(1) "b"
NULL
string(1) "b"
string(1) "b"
