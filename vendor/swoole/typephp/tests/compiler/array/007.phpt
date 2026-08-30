--TEST--
Array 007
--FILE--
<?php
$list = [];
for ($i = 0; $i< 3; $i++) {
    $keys = array_keys([$i => true]);
    $list = array_merge($list, $keys);
}
var_dump($list);
?>
--EXPECT--
array(3) {
  [0]=>
  int(0)
  [1]=>
  int(1)
  [2]=>
  int(2)
}