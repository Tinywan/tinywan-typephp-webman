--TEST--
std array: complex value types
--FILE--
<?php
class StdArrayComplexValue
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
    $strings = std::array(Type::String, 2);
    $strings[0] = 321;
    var_dump($strings[0]);

    $arrays = std::array(Type::Array, 2);
    $arrays[0] = ["name" => "array", "value" => 64];
    $array = $arrays[0];
    var_dump($array["name"]);
    var_dump($array["value"]);

    $objects = std::array(Type::Object, 2);
    $objects[0] = new StdArrayComplexValue(28);
    var_dump($objects[0] instanceof StdArrayComplexValue);
    $object = $objects[0]->toObject(StdArrayComplexValue::class);
    var_dump($object->getValue());

    $variants = std::array(Type::Any, 2);
    $variants[0] = 123;
    $variants[1] = "variant";
    var_dump($variants[0]);
    var_dump($variants[1]);
}
?>
--EXPECT--
string(3) "321"
string(5) "array"
int(64)
bool(true)
int(28)
int(123)
string(7) "variant"
