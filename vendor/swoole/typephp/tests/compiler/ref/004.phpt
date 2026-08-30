--TEST--
ref 004
--FILE--
<?php
function main()
{
    $a = [1, 2, 3];
    $b = [4, 5];
    $a[] = &$b;
    var_dump($a);

    $a[3][] = 10;
    var_dump($b);
}
?>
--EXPECT--
array(4) {
  [0]=>
  int(1)
  [1]=>
  int(2)
  [2]=>
  int(3)
  [3]=>
  &array(2) {
    [0]=>
    int(4)
    [1]=>
    int(5)
  }
}
array(3) {
  [0]=>
  int(4)
  [1]=>
  int(5)
  [2]=>
  int(10)
}