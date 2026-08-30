--TEST--
postinc/dec
--FILE--
<?php
$a = 2;
$b = 5;

$a++;
$b--;
var_dump($a);
var_dump($b);
?>
--EXPECT--
int(3)
int(4)
