--TEST--
Comparison Operators
--FILE--
<?php
// Test comparison operators
$a = 5;
$b = 10;
$c = 5;

// Equal (==)
var_dump($a == $c); // true
var_dump($a == $b); // false

// Identical (===)
var_dump($a === $c); // true
var_dump($a === "5"); // false

// Not equal (!=, <>)
var_dump($a != $b); // true
var_dump($a <> $c); // false

// Not identical (!==)
var_dump($a !== "5"); // true
var_dump($a !== $c); // false

// Greater than
var_dump($b > $a); // true
var_dump($a > $c); // false

// Less than
var_dump($a < $b); // true
var_dump($a < $c); // false

// Greater than or equal
var_dump($a >= $c); // true
var_dump($b >= $a); // true
var_dump($a >= $b); // false

// Less than or equal
var_dump($a <= $c); // true
var_dump($a <= $b); // true
var_dump($b <= $a); // false

// Test with different data types
$str_num = "10";
$int_num = 10;
var_dump($str_num == $int_num); // true (loose comparison)
var_dump($str_num === $int_num); // false (strict comparison)

// Test comparisons with strings
$str1 = "apple";
$str2 = "banana";
$str3 = "apple";

var_dump($str1 < $str2); // true (lexicographic comparison)
var_dump($str1 == $str3); // true
var_dump($str1 > $str3); // false

// Test comparisons with floats
$float1 = 1.5;
$float2 = 1.7;
$float3 = 1.5;

var_dump($float1 < $float2); // true
var_dump($float1 == $float3); // true
var_dump($float1 > $float2); // false

// Test boolean comparisons
$bool1 = true;
$bool2 = false;
$int1 = 1;
$int0 = 0;

var_dump($bool1 == $int1); // true
var_dump($bool2 == $int0); // true
var_dump($bool1 === $int1); // false (different types)

// Test in conditional contexts
if (5 > 3) {
    $result1 = "5 is greater than 3";
}
var_dump($result1);

if (2 < 1) {
    $result2 = "This won't execute";
} else {
    $result2 = "2 is not less than 1";
}
var_dump($result2);

// Test complex comparisons
$x = 15;
$y = 20;
$z = 15;

$complex1 = ($x < $y) && ($x == $z);
var_dump($complex1);

$complex2 = ($x > $y) || ($x == $z);
var_dump($complex2);

$complex3 = !($x > $y);
var_dump($complex3);

// Test null comparisons
$null_val = null;
$zero_val = 0;
$empty_str = "";

var_dump($null_val == $zero_val); // true
var_dump($null_val === $zero_val); // false
echo '$zero_val == $empty_str:' . PHP_EOL;
var_dump($zero_val == $empty_str); // true
var_dump($zero_val === $empty_str); // false

$num_a = 999;
$num_b = 999;
var_dump('int==int', $num_a == $num_b);
var_dump('int!=int', $num_a != $num_b);
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(false)
string(19) "5 is greater than 3"
string(20) "2 is not less than 1"
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
$zero_val == $empty_str:
bool(false)
bool(false)
string(8) "int==int"
bool(true)
string(8) "int!=int"
bool(false)