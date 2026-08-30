--TEST--
abs edge cases: PHP_INT_MIN and -0.0
--FILE--
<?php
$value = any(PHP_INT_MIN);
var_dump(abs($value));
var_dump(abs(-0.0));
var_dump(abs(0));
var_dump(abs(-5));
var_dump(abs(3.14));
?>
--EXPECT--
float(9.223372036854776E+18)
float(0)
int(0)
int(5)
float(3.14)
