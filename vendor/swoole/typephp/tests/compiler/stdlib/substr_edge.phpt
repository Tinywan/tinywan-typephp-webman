--TEST--
substr edge cases: negative offset and length
--FILE--
<?php
var_dump(substr("hello", -1));
var_dump(substr("hello", -10));
var_dump(substr("hello", 0, -1));
var_dump(substr("hello", 0, -10));
var_dump(substr("hello", 10));
var_dump(substr("hello", 0, 100));
?>
--EXPECT--
string(1) "o"
string(5) "hello"
string(4) "hell"
string(0) ""
string(0) ""
string(5) "hello"
