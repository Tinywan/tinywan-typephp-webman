--TEST--
Array 003
--FILE--
<?php
$array2 = [
    'foo', 'bar', 'baz',
];
var_dump($array2);
?>
--EXPECT--
array(3) {
  [0]=>
  string(3) "foo"
  [1]=>
  string(3) "bar"
  [2]=>
  string(3) "baz"
}