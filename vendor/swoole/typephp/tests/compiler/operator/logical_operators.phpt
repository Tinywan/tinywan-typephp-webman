--TEST--
Logical Operators
--FILE--
<?php
// Test logical operators
$a = true;
$b = false;
$x = 5;
$y = 10;

// AND operator (&&)
var_dump($a && $a); // true
var_dump($a && $b); // false
var_dump(($x > 0) && ($y > 0)); // true
var_dump(($x > 0) && ($y < 0)); // false

// OR operator (||)
var_dump($a || $b); // true
var_dump($b || $b); // false
var_dump(($x > 10) || ($y > 5)); // true
var_dump(($x > 10) || ($y < 5)); // false

// NOT operator (!)
var_dump(!$a); // false
var_dump(!$b); // true
var_dump(!($x > 0)); // false
var_dump(!($x > 10)); // true

// XOR operator (XOR)
var_dump('$a xor $b', $a xor $b); // true
var_dump($a xor $a); // false
var_dump($b xor $b); // false
var_dump(($x > 0) xor ($y < 0)); // true

// Alternative operators
var_dump($a and $b); // false (lower precedence)
var_dump($a or $b); // true (lower precedence)
var_dump(!$b); // true (lower precedence)

// Complex logical expressions
$age = 25;
$has_license = true;
$has_car = false;

$can_drive = ($age >= 18) && $has_license;
var_dump($can_drive);

$can_travel = $can_drive || $has_car;
var_dump($can_travel);

$should_buy_car = !$has_car && $can_drive;
var_dump($should_buy_car);

// Test with numeric values (0 is false, non-zero is true)
$num1 = 0;
$num2 = 5;
$num3 = -3;

var_dump($num1 && $num2); // false
var_dump($num2 && $num3); // true
var_dump($num1 || $num2); // true
var_dump(!$num1); // true
var_dump(!$num2); // false

// Test with strings (empty string is false, non-empty is true)
$str1 = "";
$str2 = "hello";

var_dump($str1 || $str2); // true
var_dump(!$str1); // true

// Test precedence: && has higher precedence than ||
$result3 = true || false && false;
var_dump($result3); // true (true || (false && false))

$result4 = (true || false) && false;
var_dump($result4); // false

// Test with null and other falsy values
$null_val = null;
$falsy1 = 0;
$falsy2 = "";

var_dump(!$null_val); // true
var_dump($falsy1 || $falsy2); // false

$message = "";
if ($x > 0 && $y > 0) {
    $message = "Both x and y are positive";
}
var_dump($message);

if ($x < 0 || $y < 0) {
    $message2 = "Either x or y is negative";
} else {
    $message2 = "Neither x nor y is negative";
}
var_dump($message2);
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
bool(false)
bool(true)
bool(false)
bool(true)
string(9) "$a xor $b"
bool(true)
bool(false)
bool(false)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
bool(true)
bool(true)
bool(false)
bool(true)
bool(false)
string(25) "Both x and y are positive"
string(27) "Neither x nor y is negative"
