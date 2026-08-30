--TEST--
count: ArrayObject
--FILE--
<?php
$array = new ArrayObject();
$array[] = 1;
$array[] = 2;
$array[] = 3;
var_dump(count($array));
?>
--EXPECT--
int(3)
