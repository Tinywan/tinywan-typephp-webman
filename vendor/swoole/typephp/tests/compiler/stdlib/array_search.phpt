--TEST--
in_array / array_search / array_key_exists / array_keys
--FILE--
<?php
// in_array
var_dump(in_array(2, [1, 2, 3]));
var_dump(in_array("a", [1, 2, 3]));
var_dump(in_array(0, ["hello", "world"]));
var_dump(in_array("1", [1, 2, 3], true));

// array_search
var_dump(array_search(2, [1, 2, 3]));
var_dump(array_search("a", [1, 2, 3]));
var_dump(array_search("1", [1, 2, 3], true));
var_dump(array_search(2, ["a" => 1, "b" => 2, "c" => 3]));

// array_key_exists
var_dump(array_key_exists("a", ["a" => 1, "b" => 2]));
var_dump(array_key_exists("c", ["a" => 1, "b" => 2]));
var_dump(array_key_exists(0, [1, 2, 3]));
var_dump(array_key_exists(3, [1, 2, 3]));

// array_keys
var_dump(array_keys(["a" => 1, "b" => 2, "c" => 3]));
var_dump(array_keys([1, 2, 3]));
var_dump(array_keys([]));
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(false)
int(1)
bool(false)
bool(false)
string(1) "b"
bool(true)
bool(false)
bool(true)
bool(false)
array(3) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
  [2]=>
  string(1) "c"
}
array(3) {
  [0]=>
  int(0)
  [1]=>
  int(1)
  [2]=>
  int(2)
}
array(0) {
}
