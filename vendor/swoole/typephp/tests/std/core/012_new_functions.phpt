--TEST--
New stdlib functions - realpath, gettype, uniqid, ucfirst, reset, end, ksort, array_reverse, array_diff, max, min, floor, ceil
--FILE--
<?php

echo "== gettype() ==\n";
var_dump(gettype(123));
var_dump(gettype("hello"));
var_dump(gettype(3.14));
var_dump(gettype(true));
var_dump(gettype([1,2,3]));
var_dump(gettype(null));

echo "== ucfirst() ==\n";
echo ucfirst("hello") . "\n";
echo ucfirst("world") . "\n";
echo ucfirst("") . "\n";
echo ucfirst("a") . "\n";
echo ucfirst("123abc") . "\n";

echo "== floor() / ceil() ==\n";
var_dump(floor(3.14));
var_dump(ceil(3.14));
var_dump(floor(-3.14));
var_dump(ceil(-3.14));
var_dump(floor(5));
var_dump(ceil(5));

echo "== max() / min() ==\n";
var_dump(max([1, 2, 3]));
var_dump(min([1, 2, 3]));
var_dump(max([-1, 0, 1]));
var_dump(min([-1, 0, 1]));
var_dump(max(["a", "b", "c"]));
var_dump(min(["a", "b", "c"]));

echo "== uniqid() ==\n";
$id = uniqid();
var_dump(strlen($id) > 10);
$id2 = uniqid("pref_");
var_dump(strncmp($id2, "pref_", 5) === 0);
$id3 = uniqid("", true);
var_dump(strlen($id3) > 20);

echo "== reset() / end() ==\n";
$arr = [1, 2, 3];
var_dump(reset($arr));
var_dump(end($arr));
$arr2 = ['a' => 10, 'b' => 20, 'c' => 30];
var_dump(reset($arr2));
var_dump(end($arr2));

echo "== ksort() ==\n";
$arr3 = ['c' => 3, 'a' => 1, 'b' => 2];
ksort($arr3);
echo implode(',', array_keys($arr3)) . "\n";
echo implode(',', array_values($arr3)) . "\n";

echo "== array_reverse() ==\n";
print_r(array_reverse([1, 2, 3]));
print_r(array_reverse(['a' => 1, 'b' => 2, 'c' => 3]));
print_r(array_reverse(['a' => 1, 'b' => 2, 'c' => 3], true));

echo "== array_diff() ==\n";
print_r(array_diff([1, 2, 3], [2, 4]));
print_r(array_diff(['a' => 1, 'b' => 2, 'c' => 3], [2]));
print_r(array_diff([1, 2, 3], [1, 2, 3]));

echo "== realpath() ==\n";
$path = realpath("/");
var_dump($path !== false);

?>
--EXPECT--
== gettype() ==
string(7) "integer"
string(6) "string"
string(6) "double"
string(7) "boolean"
string(5) "array"
string(4) "NULL"
== ucfirst() ==
Hello
World

A
123abc
== floor() / ceil() ==
float(3)
float(4)
float(-4)
float(-3)
float(5)
float(5)
== max() / min() ==
int(3)
int(1)
int(1)
int(-1)
string(1) "c"
string(1) "a"
== uniqid() ==
bool(true)
bool(true)
bool(true)
== reset() / end() ==
int(1)
int(3)
int(10)
int(30)
== ksort() ==
a,b,c
1,2,3
== array_reverse() ==
Array
(
    [0] => 3
    [1] => 2
    [2] => 1
)
Array
(
    [c] => 3
    [b] => 2
    [a] => 1
)
Array
(
    [c] => 3
    [b] => 2
    [a] => 1
)
== array_diff() ==
Array
(
    [0] => 1
    [2] => 3
)
Array
(
    [a] => 1
    [c] => 3
)
Array
(
)
== realpath() ==
bool(true)
