--TEST--
Bug #33989 (extract($GLOBALS,EXTR_REFS) crashes PHP)
--SKIPIF--
<?php
if (true) die("skip AOT does not support extract()");
?>

--FILE--
<?php
$a="a";
extract($GLOBALS, EXTR_REFS);
echo "ok\n";
?>
--EXPECT--
ok
