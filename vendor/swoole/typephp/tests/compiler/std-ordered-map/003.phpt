--TEST--
std ordered_map: complex value types
--FILE--
<?php
class StdMapComplexValue
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
    $strings = std::ordered_map(Type::Int, Type::String);
    $strings[1] = 456;
    var_dump($strings[1]);

    $arrays = std::ordered_map(Type::Int, Type::Array);
    $arrays[2] = ["name" => "map", "value" => 84];
    $array = $arrays[2];
    var_dump($array["name"]);
    var_dump($array["value"]);

    $objects = std::ordered_map(Type::String, Type::Object);
    $objects["item"] = new StdMapComplexValue(14);
    var_dump($objects["item"] instanceof StdMapComplexValue);
    $object = $objects["item"]->toObject(StdMapComplexValue::class);
    var_dump($object->getValue());

    $variants = std::ordered_map(Type::Int, Type::Any);
    $variants[3] = 12.5;
    $variants[4] = "any";
    var_dump($variants[3]);
    var_dump($variants[4]);
}
?>
--EXPECT--
string(3) "456"
string(3) "map"
int(84)
bool(true)
int(14)
float(12.5)
string(3) "any"
