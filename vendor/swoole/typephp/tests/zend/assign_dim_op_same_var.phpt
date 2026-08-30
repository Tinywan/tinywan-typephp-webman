--TEST--
Compound array assignment with same variable
--FILE--
<?php
function main() {
    $ary = [[]];
    $ary[0] += $ary;
    foreach ($ary as $v) {
        var_dump($v);
    }
}
?>
--EXPECT--
array(1) {
  [0]=>
  array(0) {
  }
}
