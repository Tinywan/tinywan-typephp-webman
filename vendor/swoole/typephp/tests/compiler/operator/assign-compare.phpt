--TEST--
assign compare
--FILE--
<?php
$t1 = 1;
$t2 = 4;
$t3 = 0;
var_dump($t2 < ($t3 = $t1 * 5));
?>
--EXPECT--
bool(true)