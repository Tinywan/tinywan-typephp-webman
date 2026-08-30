--TEST--
Assignment Operators
--FILE--
<?php
// Test assignment operators
$a = 10;
var_dump($a);

// Addition assignment
$a += 5;
var_dump($a);

// Subtraction assignment
$a -= 3;
var_dump($a);

// Multiplication assignment
$a *= 2;
var_dump($a);

// Division assignment
$a /= 4;
var_dump($a);

// Modulus assignment
$a = 17;
$a %= 5;
var_dump($a);

// Concatenation assignment
$str = "Hello";
var_dump($str);

$str .= " World";
var_dump($str);

// Test with different data types
$num = 100;
var_dump($num);

$num /= 4;
var_dump($num);

$num_float = floatval($num);
$num_float *= 1.5;
var_dump($num_float);

// Test compound operations with expressions
$x = 5;
$x += 3 * 2; // $x = $x + (3 * 2) = 5 + 6 = 11
var_dump($x);

$y = 20;
$y -= 4 + 1; // $y = $y - (4 + 1) = 20 - 5 = 15
var_dump($y);

$z = 6;
$z *= 2 ** 3; // $z = $z * (2 ** 3) = 6 * 8 = 48
var_dump($z);

// Test assignment with function results
$numbers = [1, 2, 3, 4, 5];
$sum = 0;
foreach ($numbers as $_num) {
    $sum += $_num;
}
var_dump($sum);

// Test multiple assignments
$a = $b = $c = 10;
var_dump($a);
var_dump($b);
var_dump($c);

$a += 5;
$b *= 2;
$c -= 3;
var_dump($a);
var_dump($b);
var_dump($c);

// Test assignment operators with arrays
$arr = [1, 2, 3];
var_dump($arr);

// Append to array using assignment
$arr[] = 4;
var_dump($arr);

// String concatenation with variable
$greeting = "Hello";
$name = "John";
$greeting .= ", " . $name . "!";
var_dump($greeting);

// Test assignment in conditional context
$value = 5;
$result = $value *= 2;
var_dump($value);
var_dump($result);

// Test increment and decrement in assignments
$counter = 10;
$pre_inc = ++$counter; // counter becomes 11, pre_inc gets 11
var_dump($counter);
var_dump($pre_inc);

$post_inc = $counter++; // post_inc gets 11, counter becomes 12
var_dump($counter);
var_dump($post_inc);
?>
--EXPECT--
int(10)
int(15)
int(12)
int(24)
int(6)
int(2)
string(5) "Hello"
string(11) "Hello World"
int(100)
int(25)
float(37.5)
int(11)
int(15)
int(48)
int(15)
int(10)
int(10)
int(10)
int(15)
int(20)
int(7)
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(4) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
  [3]=>
  int(4)
}
string(12) "Hello, John!"
int(10)
int(10)
int(11)
int(11)
int(12)
int(11)
