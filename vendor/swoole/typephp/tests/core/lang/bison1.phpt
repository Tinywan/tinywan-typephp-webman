--TEST--
Bison weirdness
--SKIPIF--
<?php die("skip");?>
--FILE--
<?php
echo "blah-$foo\n";
?>
--EXPECTF--
Warning: Undefined variable $foo in %s on line %d
blah-
