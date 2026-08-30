--TEST--
intdiv edge cases: division by zero and PHP_INT_MIN/-1
--FILE--
<?php
try { var_dump(intdiv(10, 0)); } catch (DivisionByZeroError $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }
try { var_dump(intdiv(PHP_INT_MIN, -1)); } catch (ArithmeticError $e) { echo get_class($e) . ": " . $e->getMessage() . "\n"; }
var_dump(intdiv(10, 3));
var_dump(intdiv(-10, 3));
?>
--EXPECT--
DivisionByZeroError: Division by zero
ArithmeticError: Division of PHP_INT_MIN by -1 is not an integer
int(3)
int(-3)
