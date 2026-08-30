--TEST--
explode edge cases: empty delimiter and limit
--FILE--
<?php
try { var_dump(explode("", "test")); } catch (ValueError $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }
var_dump(explode(",", ""));
var_dump(explode(",", "a,b,c", 0));
var_dump(explode(",", "a,b,c", -1));
?>
--EXPECT--
ValueError: explode(): Argument #1 ($separator) must not be empty
array(1) {
  [0]=>
  string(0) ""
}
array(1) {
  [0]=>
  string(5) "a,b,c"
}
array(2) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "b"
}
