--TEST--
property assign op
--FILE--
<?php
$o = new stdClass;
$o->a = 1024;
$o->a *= 2;
var_dump($o->a);
?>
--EXPECT--
int(2048)
