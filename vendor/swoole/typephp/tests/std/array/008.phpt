--TEST--
array_combine: non-int/non-string key handling
--FILE--
<?php
function main() {
    // Basic combine
    $keys = ["a", "b", "c"];
    $values = [1, 2, 3];
    $r = array_combine($keys, $values);
    var_dump($r);

    // Integer keys
    $keys2 = [10, 20, 30];
    $values2 = ["a", "b", "c"];
    $r = array_combine($keys2, $values2);
    var_dump($r);

    // Float keys become string keys
    $keys3 = [1.5, 2.5];
    $values3 = ["first", "second"];
    $r = array_combine($keys3, $values3);
    var_dump($r);

    // Null key becomes empty string key
    $keys4 = [null];
    $values4 = ["null_key"];
    $r = array_combine($keys4, $values4);
    var_dump($r);

    // Boolean key: true→"1"→1 (int via symtable), false→"" (string)
    $keys5 = [true, false];
    $values5 = ["true_val", "false_val"];
    $r = array_combine($keys5, $values5);
    var_dump($r);
}
?>
--EXPECT--
array(3) {
  ["a"]=>
  int(1)
  ["b"]=>
  int(2)
  ["c"]=>
  int(3)
}
array(3) {
  [10]=>
  string(1) "a"
  [20]=>
  string(1) "b"
  [30]=>
  string(1) "c"
}
array(2) {
  ["1.5"]=>
  string(5) "first"
  ["2.5"]=>
  string(6) "second"
}
array(1) {
  [""]=>
  string(8) "null_key"
}
array(2) {
  [1]=>
  string(8) "true_val"
  [""]=>
  string(9) "false_val"
}
