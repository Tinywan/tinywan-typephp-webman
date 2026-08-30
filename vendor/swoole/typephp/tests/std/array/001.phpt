--TEST--
array_diff: string-based comparison
--FILE--
<?php
function main() {
    // array_diff compares values as strings, not loose equality
    $a = [1, 2, 3, "1"];
    $b = ["1"];
    $r = array_diff($a, $b);
    var_dump($r);

    // 0 and false both become "" as string, but 0 != false via string compare
    // 0 → "0", false → "" — they differ as strings, so false NOT excluded
    $c = [0, 1];
    $d = [false];
    $r = array_diff($c, $d);
    var_dump($r);

    // Null becomes "" as string
    $e = [null, 1, 2];
    $f = [""];
    $r = array_diff($e, $f);
    var_dump($r);

    // Keys preserved
    $g = ["a" => "hello", "b" => "world"];
    $h = ["hello"];
    $r = array_diff($g, $h);
    var_dump($r);

    // Numeric strings match integers (both become same string)
    $i = [100, 200, 300];
    $j = ["200"];
    $r = array_diff($i, $j);
    var_dump($r);
}
?>
--EXPECT--
array(2) {
  [1]=>
  int(2)
  [2]=>
  int(3)
}
array(2) {
  [0]=>
  int(0)
  [1]=>
  int(1)
}
array(2) {
  [1]=>
  int(1)
  [2]=>
  int(2)
}
array(1) {
  ["b"]=>
  string(5) "world"
}
array(2) {
  [0]=>
  int(100)
  [2]=>
  int(300)
}
