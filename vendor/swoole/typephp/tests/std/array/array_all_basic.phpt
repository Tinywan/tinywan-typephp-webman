--TEST--
Test array_all() function : basic functionality
--SKIPIF--
--FILE--
<?php

function even($input, $key) {
  return $input % 2 === 0;
}

class SmallerTenClass {
    public static function smallerTen($input, $key) {
        return $input < 10;
    }
}

function return_nothing($value, $key) {
    // return nothing
}

function main() {
$array1 = [
    "a" => 1,
    "b" => 2,
    "c" => 3,
    "d" => 4,
    "e" => 5,
];

$array2 = [
    1, 2, 3, 4, 5
];

var_dump(array_all($array1, fn($value, $key) => $value > 0));
var_dump(array_all($array2, fn($value, $key) => $value > 0));
var_dump(array_all($array2, fn($value, $key) => $value > 1));
var_dump(array_all([], fn($value, $key) => true));

var_dump(array_all($array1, 'even'));

var_dump(array_all($array1, 'return_nothing'));

var_dump(array_all($array1, [
    'SmallerTenClass',
    'smallerTen'
]));

var_dump(array_all($array1, "SmallerTenClass::smallerTen"));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
bool(false)
bool(true)
bool(true)
