--TEST--
Test extract() function - ensure EXTR_REFS doesn't mess with isRef flag on COW references to array elements.
--SKIPIF--
<?php
if (true) die("skip AOT does not support extract()");
?>

--FILE--
<?php
$a = array('foo' => 'original.foo');
$nonref = $a['foo'];
$ref = &$a;
extract($a, EXTR_REFS);
$a['foo'] = 'changed.foo';
var_dump($nonref);
?>
--EXPECT--
string(12) "original.foo"
