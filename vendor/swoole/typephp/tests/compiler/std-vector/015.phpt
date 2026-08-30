--TEST--
std containers: abstract class value type
--FILE--
<?php
abstract class StdContainerAbstractValue
{
    abstract public function getValue(): int;
}

class StdContainerAbstractChild extends StdContainerAbstractValue
{
    public function __construct(private int $value)
    {}

    public function getValue(): int
    {
        return $this->value;
    }
}

class StdContainerAbstractOther
{
}

function std_container_abstract_mixed(mixed $value): mixed
{
    return $value;
}

function main() {
    $vector = std::vector(StdContainerAbstractValue::class);
    $vector[] = std_container_abstract_mixed(new StdContainerAbstractChild(1));
    var_dump($vector[0]->getValue());

    try {
        $vector[] = std_container_abstract_mixed(new StdContainerAbstractOther());
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(1)
The parameter `object` must be instance of class `StdContainerAbstractValue`, object of `StdContainerAbstractOther` given
