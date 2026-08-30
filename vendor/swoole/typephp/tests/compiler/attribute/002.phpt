--TEST--
Attribute： 002
--FILE--
<?php

#[MyAttribute]
class Thing
{
}

function main() {
    $o = new Thing;
    var_dump($o);

    $reflection = new ReflectionClass(Thing::class);
    $attributes = $reflection->getAttributes(MyAttribute::class);
    var_dump($attributes);
}
?>
--EXPECT--
object(Thing)#1 (0) {
}
array(1) {
  [0]=>
  object(ReflectionAttribute)#3 (1) {
    ["name"]=>
    string(11) "MyAttribute"
  }
}