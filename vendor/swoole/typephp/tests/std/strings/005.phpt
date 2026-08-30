--TEST--
highlight_string(), output buffer and error level
--SKIPIF--
--FILE--
<?php

echo "hello\n";

$string = str_repeat("A", 1024);
ini_set('error_reporting', '8192');

var_dump(error_reporting());
highlight_string($string, true);
var_dump(ob_get_contents());
var_dump(error_reporting());

echo "Done\n";
?>
--EXPECT--
hello
int(8192)
bool(false)
int(8192)
Done
