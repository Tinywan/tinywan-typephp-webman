--TEST--
crc32 / uniqid / print_r / gettype / array_is_list
--FILE--
<?php
// crc32
var_dump(crc32(""));
var_dump(crc32("hello"));
var_dump(crc32("Hello World!"));
var_dump(is_int(crc32("test")));

// uniqid
$id = uniqid();
var_dump(is_string($id) && !empty($id));
$id2 = uniqid("prefix_");
var_dump(str_starts_with($id2, "prefix_"));
$id3 = uniqid("", true);
var_dump(strlen($id3) > 13);

// print_r with return=true
var_dump(print_r("hello", true));
var_dump(str_contains(print_r([1, 2, 3], true), "[0] => 1"));
var_dump(str_contains(print_r(["a" => 1], true), "[a]"));

// gettype
var_dump(gettype(null));
var_dump(gettype(true));
var_dump(gettype(42));
var_dump(gettype(3.14));
var_dump(gettype("hello"));
var_dump(gettype([]));
var_dump(gettype(new stdClass()));

// array_is_list
var_dump(array_is_list([]));
var_dump(array_is_list([1, 2, 3]));
var_dump(array_is_list([0 => "a", 1 => "b"]));
var_dump(array_is_list([1 => "a", 0 => "b"]));
var_dump(array_is_list(["a" => 1, "b" => 2]));
?>
--EXPECT--
int(0)
int(907060870)
int(472456355)
bool(true)
bool(true)
bool(true)
bool(true)
string(5) "hello"
bool(true)
bool(true)
string(4) "NULL"
string(7) "boolean"
string(7) "integer"
string(6) "double"
string(6) "string"
string(5) "array"
string(6) "object"
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
