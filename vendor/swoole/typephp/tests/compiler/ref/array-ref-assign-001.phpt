--TEST--
array reference assignment: append and element assignment write back through reference
--FILE--
<?php
function main()
{
    $arr1 = [1, 2, 3];
    $arr2 = [&$arr1[0]];
    $arr2[0] = 123;
    $arr2[] = &$arr1[1];
    $arr2[1] = 456;
    var_dump($arr1, $arr2);
}
?>
--EXPECT--
array(3) {
  [0]=>
  &int(123)
  [1]=>
  &int(456)
  [2]=>
  int(3)
}
array(2) {
  [0]=>
  &int(123)
  [1]=>
  &int(456)
}
