--TEST--
item reference
--FILE--
<?php
$a = [5, 3, 1, 9, 2, 4, 3];
$b = [$a, $a];
sort($b[0]);
var_dump($b);
?>
--EXPECT--
array(2) {
  [0]=>
  array(7) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(3)
    [3]=>
    int(3)
    [4]=>
    int(4)
    [5]=>
    int(5)
    [6]=>
    int(9)
  }
  [1]=>
  array(7) {
    [0]=>
    int(5)
    [1]=>
    int(3)
    [2]=>
    int(1)
    [3]=>
    int(9)
    [4]=>
    int(2)
    [5]=>
    int(4)
    [6]=>
    int(3)
  }
}