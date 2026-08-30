--TEST--
Array 003
--FILE--
<?php
$array2 = [
    'foo' => 100, 'bar' => 'php', 'baz' => true,
];
var_dump($array2);
?>
--EXPECT--
array(3) {
  ["foo"]=>
  int(100)
  ["bar"]=>
  string(3) "php"
  ["baz"]=>
  bool(true)
}