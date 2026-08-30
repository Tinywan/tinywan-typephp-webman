--TEST--
assign compare
--FILE--
<?php
// Test spaceship operator (PHP 7+)
$spaceship1 = 5 <=> 10; // -1
var_dump($spaceship1);

$spaceship2 = 10 <=> 10; // 0
var_dump($spaceship2);

$spaceship3 = 15 <=> 10; // 1
var_dump($spaceship3);
?>
--EXPECT--
int(-1)
int(0)
int(1)