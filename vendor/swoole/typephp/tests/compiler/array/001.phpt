--TEST--
Array 001
--FILE--
<?php
$array1 = [1,2,3];
var_dump(count($array1));

$array2 = [
    99 => 'foo',
    1000 => 'bar',
];
var_dump(count($array2));
?>
--EXPECT--
int(3)
int(2)