--TEST--
Nested unset removes numeric string keys created in an empty array (#114)
--FILE--
<?php
function main(): void {
    $a = [];
    $a['1']['1'] = '1';
    $a['1']['2'] = '2';
    $a['1']['3'] = '3';
    var_dump($a);
    unset($a['1']['1']);
    unset($a['1']['2']);
    var_dump($a);
}
?>
--EXPECT--
array(1) {
  [1]=>
  array(3) {
    [1]=>
    string(1) "1"
    [2]=>
    string(1) "2"
    [3]=>
    string(1) "3"
  }
}
array(1) {
  [1]=>
  array(1) {
    [3]=>
    string(1) "3"
  }
}
