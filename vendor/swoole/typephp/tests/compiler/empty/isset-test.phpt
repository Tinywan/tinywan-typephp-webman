--TEST--
isset (linked expr)
--FILE--
<?php
include __DIR__ . '/../static_property_test.inc';
$arr = array(
    array(2, 2)
);
var_dump(isset(TestClass::$default_static_property));
var_dump(isset(TestClass::$default_static_property_not_exist));
var_dump(isset($arr[0][1]));
var_dump(isset($arr[0][1][2][3]->prop[4]));
?>
--EXPECT--
bool(true)
bool(false)
bool(true)
bool(false)
