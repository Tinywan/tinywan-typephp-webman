--TEST--
Attribute： 003
--SKIPIF--
--FILE--
<?php

#[MyAttribute([1, 2, 3])]
class Thing
{
}

function main() {
    $o = new Thing;
    var_dump($o);

    $reflection = new ReflectionClass(Thing::class);
    $attributes = $reflection->getAttributes(MyAttribute::class);
    foreach ($attributes as $attribute) {
       var_dump($attribute->getName());
       var_dump($attribute->getArguments());
    }
}
?>
--EXPECT--
object(Thing)#1 (0) {
}
string(11) "MyAttribute"
array(1) {
  [0]=>
  array(3) {
    [0]=>
    int(1)
    [1]=>
    int(2)
    [2]=>
    int(3)
  }
}