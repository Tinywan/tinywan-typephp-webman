--TEST--
Basic Variables and Data Types
--FILE--
<?php
// Test scalar types
$int_var = 42;
$float_var = 3.14;
$bool_var = true;
$string_var = "Hello, World!";

var_dump($int_var);
var_dump($float_var);
var_dump($bool_var);
var_dump($string_var);

// Test variable assignment and operations
$a = 10;
$b = 3;
$sum = $a + $b;
$diff = $a - $b;
$product = $a * $b;
$quotient = $a / (float) $b;
$remainder = $a % $b;

var_dump($sum);
var_dump($diff);
var_dump($product);
var_dump($quotient);
var_dump($remainder);

// Test string operations
$str1 = "Hello";
$str2 = "World";
$concat = $str1 . " " . $str2;

var_dump($concat);
?>
--EXPECT--
int(42)
float(3.14)
bool(true)
string(13) "Hello, World!"
int(13)
int(7)
int(30)
float(3.3333333333333335)
int(1)
string(11) "Hello World"