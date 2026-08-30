--TEST--
Array 002
--FILE--
<?php
$array1 = [1, 3, 4];
$array2 = [2, 5, ...$array1, 6, ];
var_dump($array2);
?>
--EXPECT--
array(6) {
  [0]=>
  int(2)
  [1]=>
  int(5)
  [2]=>
  int(1)
  [3]=>
  int(3)
  [4]=>
  int(4)
  [5]=>
  int(6)
}
