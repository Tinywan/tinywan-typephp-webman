--TEST--
strtr() trying to replace an empty string
--SKIPIF--
<?php
echo 'skip warning message format differs under AOT (PHP Request Startup prefix)';
?>
--FILE--
<?php

var_dump(strtr("foo", ["" => "bar"]));
var_dump(strtr("foo", ["" => "bar", "x" => "y"]));

?>
--EXPECTF--
Warning: strtr(): Ignoring replacement of empty string in %s on line %d
string(3) "foo"

Warning: strtr(): Ignoring replacement of empty string in %s on line %d
string(3) "foo"
