--TEST--
Array 001
--FILE--
<?php
$inputs = array(
  null => 'v1',
  null => 'v2',
);
var_dump($inputs);
?>
--EXPECT--
array(2) {
  [0]=>
  string(2) "v1"
  [1]=>
  string(2) "v2"
}