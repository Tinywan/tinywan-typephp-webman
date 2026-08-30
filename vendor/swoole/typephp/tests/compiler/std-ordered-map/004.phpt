--TEST--
std containers: class value type accepts subclasses
--FILE--
<?php
class StdContainerClassValue
{
    public function __construct(public int $value)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }
}

class StdContainerClassValueChild extends StdContainerClassValue
{
}

class StdContainerClassValueOther
{
}

function std_container_class_value_mixed(mixed $value): mixed
{
    return $value;
}

function main() {
    $map = std::ordered_map(Type::String, StdContainerClassValue::class);
    $map["a"] = new StdContainerClassValue(1);
    $item = $map["a"];
    var_dump($item->getValue());

    $vector = std::vector(StdContainerClassValue::class);
    $vector[] = new StdContainerClassValue(2);
    var_dump($vector[0]->getValue());

    $array = std::array(StdContainerClassValue::class, 2);
    $array[0] = new StdContainerClassValue(3);
    var_dump($array[0]->getValue());

    $unordered = std::map(Type::Int, StdContainerClassValue::class);
    $unordered[1] = std_container_class_value_mixed(new StdContainerClassValue(4));
    var_dump($unordered[1]->getValue());

    try {
        $unordered[2] = std_container_class_value_mixed(new StdContainerClassValueChild(5));
        var_dump($unordered[2]->getValue());
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }

    try {
        $unordered[3] = std_container_class_value_mixed(new StdContainerClassValueOther());
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(1)
int(2)
int(3)
int(4)
int(5)
The parameter `object` must be instance of class `StdContainerClassValue`, object of `StdContainerClassValueOther` given
