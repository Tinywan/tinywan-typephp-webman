--TEST--
Defining and using constants
--FILE--
<?php

define('foo', 	2);
define('foo1',	3);

var_dump(constant('foo'));
var_dump(constant('foo1'));

?>
--EXPECTF--
int(2)
int(3)
