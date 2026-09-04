--TEST--
round: TypeError for non-numeric
--FILE--
<?php
try { round("hello"); } catch (TypeError $e) { echo $e->getMessage() . "\n"; }
var_dump(round(3.7));
?>
--EXPECT--
round(): Argument #1 ($num) must be of type int|float, string given
float(4)
