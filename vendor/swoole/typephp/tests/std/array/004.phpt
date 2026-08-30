--TEST--
array_shift: non-packed re-indexing
--FILE--
<?php
function main() {
    // Non-packed (string-keyed) array: shift re-indexes integer keys
    $a = ["a" => 1, "b" => 2, 0 => 3, 1 => 4];
    $v = array_shift($a);
    var_dump($v);
    var_dump($a);

    // Mixed keys with gaps
    $b = [5 => "first", "key" => "second", 10 => "third"];
    $v = array_shift($b);
    var_dump($v);
    var_dump($b);

    // Single element
    $c = ["only" => "one"];
    $v = array_shift($c);
    var_dump($v);
    var_dump($c);
}
?>
--EXPECT--
int(1)
array(3) {
  ["b"]=>
  int(2)
  [0]=>
  int(3)
  [1]=>
  int(4)
}
string(5) "first"
array(2) {
  ["key"]=>
  string(6) "second"
  [0]=>
  string(5) "third"
}
string(3) "one"
array(0) {
}
