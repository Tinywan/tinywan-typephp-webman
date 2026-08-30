--TEST--
strlen
--FILE--
<?php
$a = 2;
$a **= 10;
var_dump($a);

$d = pow(3, 4);
assert($d == 81);
?>
--EXPECT--
int(1024)
