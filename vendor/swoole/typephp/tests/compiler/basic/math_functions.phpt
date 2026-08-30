--TEST--
Mathematical Functions
--FILE--
<?php
// Test basic math functions
$abs = abs(-5);
var_dump($abs);

$abs_float = abs(-3.7);
var_dump($abs_float);

// Test ceil and floor
$ceil = ceil(4.2);
var_dump($ceil);

$floor = floor(4.8);
var_dump($floor);

$round = round(4.567, 2);
var_dump($round);

// Test min and max
$min = min(3, 7, 1, 9, 4);
var_dump($min);

$max = max(3, 7, 1, 9, 4);
var_dump($max);

// Test with arrays
$numbers = [5, 2, 8, 1, 9];
$min_arr = min($numbers);
var_dump($min_arr);

$max_arr = max($numbers);
var_dump($max_arr);

// Test pow function
$power = pow(2, 8);
var_dump($power);

// Test sqrt
$sqrt = sqrt(64);
var_dump($sqrt);

$sqrt_float = sqrt(2);
var_dump($sqrt_float);

// Test rand and mt_rand (within a range for predictability)
srand(12345); // For predictable results in tests
$rand1 = rand(1, 100);
var_dump($rand1);

mt_srand(12345); // For predictable results in tests
$rand2 = mt_rand(1, 100);
var_dump($rand2);

// Test number format
$number = 1234.5678;
$formatted = number_format($number, 2);
var_dump($formatted);

// Test fmod
$fmod = fmod(10.5, 3.2);
var_dump($fmod);

// Test trigonometric functions
$sin = sin(pi()/2); // Should be 1
var_dump($sin);

$cos = cos(0); // Should be 1
var_dump($cos);

$tan = tan(pi()/4); // Should be close to 1
var_dump($tan);

// Test logarithmic functions
$log = log(M_E); // Natural log of e should be 1
var_dump($log);

$log10 = log10(100); // Base-10 log of 100 should be 2
var_dump($log10);

// Test exponential
$exp = exp(1); // e^1 should be e
var_dump($exp);

// Test is_* functions
$int_val = 42;
$float_val = 3.14;
$str_val = "hello";

var_dump(is_int($int_val));
var_dump(is_float($float_val));
var_dump(is_numeric("123"));
var_dump(is_numeric("abc"));

// Test conversion functions
$hex = dechex(255);
var_dump($hex);

$bin = decbin(8);
var_dump($bin);

$oct = decoct(8);
var_dump($oct);

$back_to_dec = hexdec($hex);
var_dump($back_to_dec);
?>
--EXPECT--
int(5)
float(3.7)
float(5)
float(4)
float(4.57)
int(1)
int(9)
int(1)
int(9)
int(256)
float(8)
float(1.4142135623730951)
int(91)
int(91)
string(8) "1,234.57"
float(0.8999999999999995)
float(1)
float(1)
float(0.9999999999999999)
float(1)
float(2)
float(2.718281828459045)
bool(true)
bool(true)
bool(true)
bool(false)
string(2) "ff"
string(4) "1000"
string(2) "10"
int(255)
