--TEST--
Attribute： 001
--FILE--
<?php
#[Attribute]
class MyAttribute
{
    const VALUE = 'value';

    private $value;

    public function __construct($value = null)
    {
        $this->value = $value;
    }
}

#[MyAttribute]
#[MyAttribute(1234)]
#[MyAttribute(value: 1234)]
#[MyAttribute(MyAttribute::VALUE)]
#[MyAttribute([])]
#[MyAttribute(100 + 200)]
class Thing
{
}

#[MyAttribute(1234), MyAttribute(5678)]
class AnotherThing
{
}

function main() {
    $reflection = (new ReflectionClass(Thing::class));
    $attributes = $reflection->getAttributes();

    foreach ($attributes as $attribute) {
       var_dump($attribute->getName());
       var_dump($attribute->getArguments());
    }
}
?>
--EXPECT--
string(11) "MyAttribute"
array(0) {
}
string(11) "MyAttribute"
array(1) {
  [0]=>
  int(1234)
}
string(11) "MyAttribute"
array(1) {
  ["value"]=>
  int(1234)
}
string(11) "MyAttribute"
array(1) {
  [0]=>
  string(5) "value"
}
string(11) "MyAttribute"
array(1) {
  [0]=>
  array(0) {
  }
}
string(11) "MyAttribute"
array(1) {
  [0]=>
  int(300)
}