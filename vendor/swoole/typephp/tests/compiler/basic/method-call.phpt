--TEST--
method call
--FILE--
<?php
$date = new DateTime('2000-01-01');
$rs = $date->format('Y-m-d H:i:s');
var_dump($rs);
?>
--EXPECT--
string(19) "2000-01-01 00:00:00"
