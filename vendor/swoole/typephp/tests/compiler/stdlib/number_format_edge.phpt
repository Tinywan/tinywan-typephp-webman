--TEST--
number_format edge cases
--FILE--
<?php
// Basic usage
var_dump(number_format(1234.5678));
var_dump(number_format(1234.5678, 2));
var_dump(number_format(1234.5678, 2, ",", "."));
var_dump(number_format(1234.5678, 2, ".", ""));

// Zero and negative
var_dump(number_format(0));
var_dump(number_format(-1234.56));
var_dump(number_format(-1234.56, 1));

// Large numbers
var_dump(number_format(1000000));
var_dump(number_format(1000000.5, 1));

// Integer input
var_dump(number_format(42));
var_dump(number_format(42, 3));
?>
--EXPECT--
string(5) "1,235"
string(8) "1,234.57"
string(8) "1.234,57"
string(7) "1234.57"
string(1) "0"
string(6) "-1,235"
string(8) "-1,234.6"
string(9) "1,000,000"
string(11) "1,000,000.5"
string(2) "42"
string(6) "42.000"
