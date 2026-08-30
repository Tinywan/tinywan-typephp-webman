--TEST--
array_intersect: string-based comparison
--FILE--
<?php
function main() {
    // array_intersect compares values as strings
    $a = [1, 2, 3, "1"];
    $b = ["1", 4];
    $r = array_intersect($a, $b);
    var_dump($r);

    // Numeric strings match integers
    $c = [100, 200, 300];
    $d = ["200", "500"];
    $r = array_intersect($c, $d);
    var_dump($r);

    // Keys preserved from first array
    $e = ["a" => "hello", "b" => "world"];
    $f = ["world", "extra"];
    $r = array_intersect($e, $f);
    var_dump($r);

    // Empty result
    $g = [1, 2, 3];
    $h = [4, 5, 6];
    $r = array_intersect($g, $h);
    var_dump($r);
}
?>
--EXPECT--
array(2) {
  [0]=>
  int(1)
  [3]=>
  string(1) "1"
}
array(1) {
  [1]=>
  int(200)
}
array(1) {
  ["b"]=>
  string(5) "world"
}
array(0) {
}
