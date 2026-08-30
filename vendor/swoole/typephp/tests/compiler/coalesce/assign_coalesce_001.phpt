--TEST--
assign coalesce
--FILE--
<?php
$a = 123;
$a ??= 456;
var_dump($a);

$b = null;
$b ??= 'foo';
var_dump($b);
?>
--EXPECT--
int(123)
string(3) "foo"
