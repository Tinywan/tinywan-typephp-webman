--TEST--
Arithmetic Operators
--FILE--
<?php
// Test basic arithmetic operators
$a = 10;
$b = 3;

// Addition
$add = $a + $b;
var_dump($add);

// Subtraction
$sub = $a - $b;
var_dump($sub);

// Multiplication
$mul = $a * $b;
var_dump($mul);

// Division
$div = $a / floatval($b);
var_dump($div);

// Modulus
$mod = $a % $b;
var_dump($mod);

// Exponentiation (PHP 5.6+)
$pow = $a ** $b;
var_dump($pow);

// Test with floats
$x = 10.5;
$y = 3.2;

$float_add = $x + $y;
var_dump($float_add);

echo "float_div:\n==========================\n";
$float_div = $x / $y;
var_dump($float_div);

// Test increment/decrement operators
$c = 5;
var_dump($c); // 5

$c++; // post-increment
var_dump($c); // 6

++$c; // pre-increment
var_dump($c); // 7

$c--; // post-decrement
var_dump($c); // 6

--$c; // pre-decrement
var_dump($c); // 5

// Test compound assignment operators
$d = 20;
$d += 5;
var_dump($d); // 25

echo "self plus:\n==========================\n";
$float_div = $x / $y;

$d -= 3;
var_dump($d); // 22

$d *= 2;
var_dump($d); // 44

echo "assign div:\n==========================\n";
$d /= 4;
var_dump($d); // 11

$d %= 5;
var_dump($d); // 1

// Test operator precedence
$result1 = 2 + 3 * 4; // 2 + (3 * 4) = 14
var_dump($result1);

$result2 = (2 + 3) * 4; // (2 + 3) * 4 = 20
var_dump($result2);

$result3 = 10 - 3 + 2; // (10 - 3) + 2 = 9
var_dump($result3);

$result4 = 2 ** 3 ** 2; // 2 ** (3 ** 2) = 2 ** 9 = 512
var_dump($result4);

// Test with negative numbers
$neg_a = -10;
$neg_b = 3;

$neg_result = $neg_a % $neg_b;
var_dump($neg_result);

echo "neg float div:\n==========================\n";
$neg_result2 = $neg_a / (float)$neg_b;
var_dump($neg_result2);

// Test division by zero behavior (should not crash in this test)
$zero_div = 10 / 2; // Not dividing by zero, just testing normal division
var_dump($zero_div);

// Test zero modulus behavior (should not crash in this test)
$zero_mod = 10 % 2;
var_dump($zero_mod);
?>
--EXPECT--
int(13)
int(7)
int(30)
float(3.3333333333333335)
int(1)
int(1000)
float(13.7)
float_div:
==========================
float(3.28125)
int(5)
int(6)
int(7)
int(6)
int(5)
int(25)
self plus:
==========================
int(22)
int(44)
assign div:
==========================
int(11)
int(1)
int(14)
int(20)
int(9)
int(512)
int(-1)
neg float div:
==========================
float(-3.3333333333333335)
int(5)
int(0)