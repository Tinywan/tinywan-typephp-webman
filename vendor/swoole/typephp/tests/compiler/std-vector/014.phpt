--TEST--
std containers: interface class value type
--FILE--
<?php
interface StdContainerInterfaceValue
{
    public function getValue(): int;
}

class StdContainerInterfaceImpl implements StdContainerInterfaceValue
{
    public function __construct(private int $value)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }
}

class StdContainerInterfaceOther
{
}

function std_container_interface_mixed(mixed $value): mixed
{
    return $value;
}

function main() {
    $vector = std::vector(StdContainerInterfaceValue::class);
    $vector[] = new StdContainerInterfaceImpl(1);
    $vector[] = std_container_interface_mixed(new StdContainerInterfaceImpl(2));
    var_dump($vector[0]->getValue());
    var_dump($vector[1]->getValue());

    $array = std::array(StdContainerInterfaceValue::class, 1);
    $array[0] = std_container_interface_mixed(new StdContainerInterfaceImpl(3));
    var_dump($array[0]->getValue());

    $map = std::map(Type::String, StdContainerInterfaceValue::class);
    $map["item"] = std_container_interface_mixed(new StdContainerInterfaceImpl(4));
    var_dump($map["item"]->getValue());

    $ordered = std::ordered_map(Type::String, StdContainerInterfaceValue::class);
    $ordered["item"] = std_container_interface_mixed(new StdContainerInterfaceImpl(5));
    var_dump($ordered["item"]->getValue());

    try {
        $vector[] = std_container_interface_mixed(new StdContainerInterfaceOther());
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
The parameter `object` must be instance of class `StdContainerInterfaceValue`, object of `StdContainerInterfaceOther` given
