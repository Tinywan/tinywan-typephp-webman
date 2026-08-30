--TEST--
isset 002
--FILE--
<?php
$array1 = [ 2000, 0, ];
$array2 = [ 2022, null, ];

var_dump(isset($array1[0], $array2[0]));
var_dump(isset($array1[1], $array2[1]));
var_dump(isset($array1[1], $array2[0]));
var_dump(isset($array1[0], $array2[1]));
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
