--TEST--
log edge cases: zero, negative, and base validation
--FILE--
<?php
var_dump(log(0));
var_dump(log(-1));
try { var_dump(log(1, 0)); } catch (ValueError $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }
var_dump(log(1, 1));
var_dump(log(8, 2));
var_dump(log(100, 10));
?>
--EXPECT--
float(-INF)
float(NAN)
ValueError: log(): Argument #2 ($base) must be greater than 0
float(NAN)
float(3)
float(2)
