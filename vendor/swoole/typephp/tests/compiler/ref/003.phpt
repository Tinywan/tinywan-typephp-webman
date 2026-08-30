--TEST--
ref 003
--FILE--
<?php
function main()
{
    $a = [1, 2, 3];
    $c = $d = $e = &$a;

    $e[] = 5;

    var_dump($c);
    var_dump($d);
    var_dump($e);
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