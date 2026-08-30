--TEST--
empty 2
--FILE--
<?php
$a = 0;
$b = $a ?: 1;
var_dump($b);

$c = 3;
$d = $c ?: 5;
var_dump($d);
?>
--EXPECT--
int(1)
int(3)
