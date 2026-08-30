--TEST--
static calls
--FILE--
<?php
$cls = 'DateTime';
$method = 'createFromFormat';
$date = $cls::$method('Y-m-d H:i:s', '2024-02-03 04:05:06');
?>
--EXPECTF--
