--TEST--
foreach by value keeps a stable array snapshot when the current element is removed
--FILE--
<?php
function main(): void
{
    $values = ['a' => 1, 'b' => 2, 'c' => 3];
    $seen = [];

    foreach ($values as $key => $value) {
        $seen[] = $key . ':' . $value;
        unset($values[$key]);
    }

    var_dump($seen);
    var_dump($values);
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(3) "a:1"
  [1]=>
  string(3) "b:2"
  [2]=>
  string(3) "c:3"
}
array(0) {
}
