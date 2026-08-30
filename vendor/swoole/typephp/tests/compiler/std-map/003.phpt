--TEST--
std map: complex value types
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
    $strings = std::map(Type::Int, Type::String);
    $strings[1] = 789;
    var_dump($strings[1]);

    $arrays = std::map(Type::String, Type::Array);
    $arrays["item"] = ["name" => "unordered", "value" => 168];
    $array = $arrays["item"];
    var_dump($array["name"]);
    var_dump($array["value"]);

    $objects = std::map(Type::Int, Type::Object);
    $objects[2] = new StdMapComplexValue(21);
    var_dump($objects[2] instanceof StdMapComplexValue);
    $object = $objects[2]->toObject(StdMapComplexValue::class);
    var_dump($object->getValue());

    $variants = std::map(Type::Int, Type::Any);
    $variants[3] = false;
    $variants[4] = "variant";
    var_dump($variants[3]);
    var_dump($variants[4]);
}
?>
--EXPECT--
string(3) "789"
string(9) "unordered"
int(168)
bool(true)
int(21)
bool(false)
string(7) "variant"
