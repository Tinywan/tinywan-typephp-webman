--TEST--
Bug #70910 (extract() breaks variable references)
--SKIPIF--
<?php
if (true) die("skip AOT does not support extract()");
?>

--FILE--
<?php
$var = 'original value';
$ref =& $var;

$hash = ['var' => 'new value'];

extract($hash);
var_dump($var === $ref);
?>
--EXPECT--
bool(true)
