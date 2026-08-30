--TEST--
array_unshift: preserves string keys
--FILE--
<?php
function main() {
    // String keys preserved, int keys re-indexed
    $a = ["a" => 1, "b" => 2];
    $c = array_unshift($a, "new1", "new2");
    var_dump($c);
    var_dump($a);

    // Mixed integer and string keys
    $b = [0 => "zero", "key" => "value"];
    array_unshift($b, "prepended");
    var_dump($b);

    // Return value is new count
    $d = [];
    $c = array_unshift($d, "x");
    var_dump($c);
    var_dump($d);
}
?>
--EXPECT--
int(4)
array(4) {
  [0]=>
  string(4) "new1"
  [1]=>
  string(4) "new2"
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
}
array(3) {
  [0]=>
  string(9) "prepended"
  [1]=>
  string(4) "zero"
  ["key"]=>
  string(5) "value"
}
int(1)
array(1) {
  [0]=>
  string(1) "x"
}
