--TEST--
array_fill edge cases: negative count
--FILE--
<?php
try { var_dump(array_fill(0, -1, "x")); } catch (ValueError $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }
var_dump(array_fill(0, 0, "x"));
var_dump(array_fill(5, 3, "banana"));
?>
--EXPECT--
ValueError: array_fill(): Argument #2 ($count) must be greater than or equal to 0
array(0) {
}
array(3) {
  [5]=>
  string(6) "banana"
  [6]=>
  string(6) "banana"
  [7]=>
  string(6) "banana"
}
