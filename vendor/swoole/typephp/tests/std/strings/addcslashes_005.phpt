--TEST--
addcslashes(); function test with warning
--SKIPIF--
--FILE--
<?php
echo addcslashes("zoo['.']", "z..A");
?>
--EXPECTF--
%s: Invalid '..'-range, '..'-range needs to be incrementing in %s on line %d
\zoo['\.']
