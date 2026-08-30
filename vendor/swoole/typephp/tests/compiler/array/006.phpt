--TEST--
Array 003
--FILE--
<?php
$array2[] = 'foo';
$array2[] = 'bar';
var_dump($array2);
?>
--EXPECT--
array(2) {
  [0]=>
  string(3) "foo"
  [1]=>
  string(3) "bar"
}