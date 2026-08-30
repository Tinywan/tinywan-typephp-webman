--TEST--
Control Structures - if/else, for/while, switch
--FILE--
<?php
// Test if/else structures
$a = 10;
$b = 20;

if ($a > $b) {
    $result = "a is greater";
} elseif ($a < $b) {
    $result = "b is greater";
} else {
    $result = "a and b are equal";
}

var_dump($result);

// Test for loop
$sum = 0;
for ($i = 1; $i <= 5; $i++) {
    $sum += $i;
}
var_dump($sum);

// Test while loop
$counter = 0;
$while_sum = 0;
while ($counter < 5) {
    $counter++;
    $while_sum += $counter;
}
var_dump($while_sum);

// Test do-while loop
$do_counter = 0;
$do_sum = 0;
do {
    $do_counter++;
    $do_sum += $do_counter;
} while ($do_counter < 5);
var_dump($do_sum);

// Test switch statement
$number = 2;
switch ($number) {
    case 1:
        $switch_result = "one";
        break;
    case 2:
        $switch_result = "two";
        break;
    case 3:
        $switch_result = "three";
        break;
    default:
        $switch_result = "other";
        break;
}
var_dump($switch_result);

// Test nested conditions
$x = 5;
$y = 10;
if ($x > 0) {
    if ($y > $x) {
        $nested_result = "y is greater than x";
    } else {
        $nested_result = "x is greater than or equal to y";
    }
} else {
    $nested_result = "x is not positive";
}
var_dump($nested_result);
?>
--EXPECT--
string(12) "b is greater"
int(15)
int(15)
int(15)
string(3) "two"
string(19) "y is greater than x"