--TEST--
str_repeat edge cases: negative times and zero
--FILE--
<?php
try { str_repeat("x", -1); } catch (ValueError $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }
var_dump(str_repeat("x", 0));
var_dump(str_repeat("x", 3));
var_dump(str_repeat("", 5));
var_dump(str_repeat("ab", 2));
?>
--EXPECT--
ValueError: str_repeat(): Argument #2 ($times) must be greater than or equal to 0
string(0) ""
string(3) "xxx"
string(0) ""
string(4) "abab"
