--TEST--
Array functions - in_array, array_search, array_key_exists, array_keys, array_values
--FILE--
<?php

echo "== in_array ==\n";
var_dump(in_array(2, [1, 2, 3]));
var_dump(in_array('2', [1, 2, 3], true));
var_dump(in_array(4, [1, 2, 3]));

echo "== array_search ==\n";
var_dump(array_search(2, [1, 2, 3]));
var_dump(array_search('2', [1, 2, 3], true));
var_dump(array_search(4, [1, 2, 3]));

echo "== array_key_exists ==\n";
$arr = ['a' => 1, 'b' => 2, 'c' => null];
var_dump(array_key_exists('a', $arr));
var_dump(array_key_exists('c', $arr));
var_dump(array_key_exists('d', $arr));
var_dump(array_key_exists(0, [10, 20, 30]));

echo "== array_keys ==\n";
print_r(array_keys(['a' => 1, 'b' => 2, 'c' => 3]));
print_r(array_keys([10, 20, 30]));
print_r(array_keys(['a' => 1, 'b' => 2, 'c' => 2], 2));
print_r(array_keys(['a' => 2, 'b' => '2', 'c' => 2], '2', true));

echo "== array_values ==\n";
print_r(array_values(['a' => 1, 'b' => 2, 'c' => 3]));
print_r(array_values([10, 20, 30]));
print_r(array_values(['x' => 1, 'y' => 2, 'z' => 3]));

?>
--EXPECT--
== in_array ==
bool(true)
bool(false)
bool(false)
== array_search ==
int(1)
bool(false)
bool(false)
== array_key_exists ==
bool(true)
bool(true)
bool(false)
bool(true)
== array_keys ==
Array
(
    [0] => a
    [1] => b
    [2] => c
)
Array
(
    [0] => 0
    [1] => 1
    [2] => 2
)
Array
(
    [0] => b
    [1] => c
)
Array
(
    [0] => b
)
== array_values ==
Array
(
    [0] => 1
    [1] => 2
    [2] => 3
)
Array
(
    [0] => 10
    [1] => 20
    [2] => 30
)
Array
(
    [0] => 1
    [1] => 2
    [2] => 3
)
