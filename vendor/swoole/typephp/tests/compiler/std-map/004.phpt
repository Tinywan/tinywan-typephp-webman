--TEST--
std map: class value type accepts subclasses
--FILE--
<?php
class StdMapClassValue
{
    public function __construct(public int $value)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }
}

class StdMapClassValueChild extends StdMapClassValue
{
}

function std_map_class_value_mixed(mixed $value): mixed
{
    return $value;
}

function main() {
    $map = std::map(Type::String, StdMapClassValue::class);
    $map["a"] = new StdMapClassValue(1);
    var_dump($map["a"]->getValue());

    $map["b"] = std_map_class_value_mixed(new StdMapClassValue(2));
    var_dump($map["b"]->getValue());

    try {
        $map["c"] = std_map_class_value_mixed(new StdMapClassValueChild(3));
        var_dump($map["c"]->getValue());
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(1)
int(2)
int(3)
