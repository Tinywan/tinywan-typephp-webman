--TEST--
Array 003
--FILE--
<?php
$key = 100;

$array2 = [
    $key => 'foo',
    1000 => 'bar',
    0 => 'baz',
];
var_dump($array2);
?>
--EXPECT--
array(3) {
  [100]=>
  string(3) "foo"
  [1000]=>
  string(3) "bar"
  [0]=>
  string(3) "baz"
}