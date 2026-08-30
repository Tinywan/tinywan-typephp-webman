--TEST--
Explicit numeric-to-string casts work with string functions in strict mode
--FILE--
<?php
ini_set('precision', 17);
$s = (string) 12345678;
var_dump(strlen($s));
var_dump(strrev($s));
var_dump(strlen((string) 3.1415926));
?>
--EXPECT--
int(8)
string(8) "87654321"
int(18)
