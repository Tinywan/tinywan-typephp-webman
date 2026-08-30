--TEST--
strlen
--FILE--
<?php
$str = "hello world";
var_dump(strlen($str));
?>
--EXPECT--
int(11)
