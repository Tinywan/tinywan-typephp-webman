--TEST--
ref 002
--FILE--
<?php
function main()
{
    $a = [1, 2, 3];
    $b = &$a;
    $b[] = 5;
    var_dump($a);
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
  int(5)
}