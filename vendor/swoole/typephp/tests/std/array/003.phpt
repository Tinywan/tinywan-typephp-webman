--TEST--
array_unique: sort flags
--FILE--
<?php
function main() {
    // SORT_STRING (default) - string comparison, 1 and "1" match
    $a = ["a" => 1, "b" => "1", "c" => 2, "d" => "2"];
    $r = array_unique($a, SORT_STRING);
    var_dump($r);

    // SORT_REGULAR - uses zend_compare, 1 and "1" match
    $b = ["a" => 1, "b" => "1", "c" => 2, "d" => "2"];
    $r = array_unique($b, SORT_REGULAR);
    var_dump($r);

    // SORT_NUMERIC - numeric comparison
    $c = ["a" => 1, "b" => "1.0", "c" => 2, "d" => "2.5"];
    $r = array_unique($c, SORT_NUMERIC);
    var_dump($r);

    // Keys preserved (first occurrence kept)
    $d = ["a" => "red", "b" => "green", "c" => "red"];
    $r = array_unique($d);
    var_dump($r);
}
?>
--EXPECT--
array(2) {
  ["a"]=>
  int(1)
  ["c"]=>
  int(2)
}
array(2) {
  ["a"]=>
  int(1)
  ["c"]=>
  int(2)
}
array(3) {
  ["a"]=>
  int(1)
  ["c"]=>
  int(2)
  ["d"]=>
  string(3) "2.5"
}
array(2) {
  ["a"]=>
  string(3) "red"
  ["b"]=>
  string(5) "green"
}
