--TEST--
ksort: flag handling
--FILE--
<?php
function main() {
    // SORT_REGULAR (default) — string comparison of keys
    $a = ["c" => 1, "a" => 2, "b" => 3];
    ksort($a);
    var_dump($a);

    // SORT_NUMERIC — numeric key comparison
    $b = ["10" => "ten", "2" => "two", "1" => "one"];
    ksort($b, SORT_NUMERIC);
    var_dump($b);

    // Mixed int and string keys
    $c = ["b" => 1, 0 => 2, "a" => 3];
    ksort($c);
    var_dump($c);
}
?>
--EXPECT--
array(3) {
  ["a"]=>
  int(2)
  ["b"]=>
  int(3)
  ["c"]=>
  int(1)
}
array(3) {
  [1]=>
  string(3) "one"
  [2]=>
  string(3) "two"
  [10]=>
  string(3) "ten"
}
array(3) {
  [0]=>
  int(2)
  ["a"]=>
  int(3)
  ["b"]=>
  int(1)
}
