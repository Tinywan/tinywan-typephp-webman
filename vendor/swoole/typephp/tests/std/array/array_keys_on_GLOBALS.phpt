--TEST--
Using array_keys() on $GLOBALS
--SKIPIF--
<?php
if (true) die("skip AOT has a known issue with this test pattern");
?>

--FILE--
<?php

$foo = 'bar';
unset($foo);
var_dump(in_array('foo', array_keys($GLOBALS)));

?>
--EXPECT--
bool(false)
