--TEST--
Bug #71806 (php_strip_whitespace() fails on some numerical values)
--SKIPIF--
<?php
echo 'skip AOT limitation';
?>
--FILE--
<?php

echo php_strip_whitespace(__DIR__ . '/bug71806.data');

?>
--EXPECT--
<?php
 echo 098 ;
