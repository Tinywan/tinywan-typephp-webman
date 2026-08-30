--TEST--
std vector: complex value types
--FILE--
<?php
class StdVectorComplexValue
{
    public function __construct(public int $value)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }
}

function main() {
    $strings = std::vector(Type::String);
    $strings[] = 123;
    var_dump($strings[0]);

    $arrays = std::vector(Type::Array);
    $arrays[] = ["name" => "vector", "value" => 42];
    $array = $arrays[0];
    var_dump($array["name"]);
    var_dump($array["value"]);

    $objects = std::vector(Type::Object);
    $objects[] = new StdVectorComplexValue(7);
    var_dump($objects[0] instanceof StdVectorComplexValue);
    $object = $objects[0]->toObject(StdVectorComplexValue::class);
    var_dump($object->getValue());

    $variants = std::vector(Type::Any);
    $variants[] = 99;
    $variants[] = "mixed";
    var_dump($variants[0]);
    var_dump($variants[1]);
}
?>
--EXPECT--
string(3) "123"
string(6) "vector"
int(42)
bool(true)
int(7)
int(99)
string(5) "mixed"
