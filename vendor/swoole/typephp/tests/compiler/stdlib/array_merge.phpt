--TEST--
array_merge with mixed typed arguments
--FILE--
<?php
class TestArrayMerge {
    public array $arr1 = ['foo'];
    public array $arr2 = ['baz'];
}
function main() {
    $o = new TestArrayMerge;
    $v = any(['bar']);
    $array = array_merge($o->arr1, $v, $o->arr2);
    var_dump($array);
}
?>
--EXPECT--
array(3) {
  [0]=>
  string(3) "foo"
  [1]=>
  string(3) "bar"
  [2]=>
  string(3) "baz"
}
