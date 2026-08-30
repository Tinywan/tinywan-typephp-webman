--TEST--
empty (linked expr)
--FILE--
<?php
include __DIR__ . '/../static_property_test.inc';
$arr = array(
    array(2, 2)
);
var_dump(empty(TestClass::$default_static_property));
var_dump(empty(TestClass::$default_static_property_not_exist));
var_dump(empty($arr[0][1]));
var_dump(empty($arr[0][1][2][3]->prop[4]));
?>
--EXPECT--
bool(false)
bool(true)
bool(false)
bool(true)
