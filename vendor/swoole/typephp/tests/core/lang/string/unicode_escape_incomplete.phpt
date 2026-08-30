--TEST--
Invalid Unicode escape sequence: Incomplete
--SKIPIF--
<?php die("skip");?>
--FILE--
<?php

var_dump("\u{blah");
?>
--EXPECTF--
Parse error: Invalid UTF-8 codepoint escape sequence in %s on line %d
