--TEST--
named args
--FILE--
<?php
function main()
{
    $array = [1, 3, 5];
    $push = [12, 33, 99];
    array_push($array, ...$push);
    var_dump($array);
}
?>
--EXPECT--
array(6) {
  [0]=>
  int(1)
  [1]=>
  int(3)
  [2]=>
  int(5)
  [3]=>
  int(12)
  [4]=>
  int(33)
  [5]=>
  int(99)
}