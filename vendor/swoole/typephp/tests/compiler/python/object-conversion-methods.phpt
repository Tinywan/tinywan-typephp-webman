--TEST--
PyObject toArray() and toValue() explicitly return PHP values
--SKIPIF--
<?php
if (!extension_loaded('phpy')) {
    die('skip phpy extension is not loaded');
}
?>
--FILE--
<?php

function main(): void
{
    $list = python\list([1, 2, 3]);
    $integer = python\int(42);

    var_dump($list->toArray());
    var_dump($list->toValue());
    var_dump($integer->toValue());
    var_dump($integer->toValue()->toInt());
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(3) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
}
int(42)
int(42)
