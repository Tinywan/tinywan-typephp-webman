--TEST--
strlen
--FILE--
<?php
list($micro, $time) = explode(" ", microtime());
var_dump($micro, $time);

list(, $time) = explode(" ", microtime());
var_dump($time);
?>
--EXPECTF--
string(%d) "%s"
string(%d) "%s"
string(%d) "%s"
