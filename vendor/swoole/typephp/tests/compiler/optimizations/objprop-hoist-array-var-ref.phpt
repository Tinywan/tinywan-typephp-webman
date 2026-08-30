--TEST--
SSA object prop: hoist array property through indirect Var handle
--FILE--
<?php
use native_types;

class Foo {
    public array $items = [];
}

function main(): void {
    $foo = new Foo();
    $foo->items[] = 'a';
    $foo->items[] = 'b';
    $foo->items[1] = 'c';

    var_dump($foo->items);
}
?>
--EXPECT--
array(2) {
  [0]=>
  string(1) "a"
  [1]=>
  string(1) "c"
}
