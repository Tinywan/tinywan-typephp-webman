--TEST--
std vector: unset
--FILE--
<?php
function main() {
    $a = std::vector(Type::Int);
    $a[] = 99;
    $a[] = 88;
    $a[] = 77;
    var_dump($a);
    unset($a[1]);
    var_dump($a);
}
?>
--EXPECT--
array(3) {
  [0]=>
  int(99)
  [1]=>
  int(88)
  [2]=>
  int(77)
}
array(3) {
  [0]=>
  int(99)
  [1]=>
  int(0)
  [2]=>
  int(77)
}