--TEST--
std vector: class value type accepts subclasses
--FILE--
<?php
class StdVectorClassValue
{
    public function __construct(public int $value)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }
}

class StdVectorClassValueChild extends StdVectorClassValue
{
}

function std_vector_class_value_mixed(mixed $value): mixed
{
    return $value;
}

function main() {
    $vector = std::vector(StdVectorClassValue::class);
    $vector[] = new StdVectorClassValue(1);
    var_dump($vector[0]->getValue());

    $vector[] = std_vector_class_value_mixed(new StdVectorClassValue(2));
    var_dump($vector[1]->getValue());

    try {
        $vector[] = std_vector_class_value_mixed(new StdVectorClassValueChild(3));
        var_dump($vector[2]->getValue());
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(1)
int(2)
int(3)
