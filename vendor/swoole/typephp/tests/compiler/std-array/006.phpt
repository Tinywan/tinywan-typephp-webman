--TEST--
std array: class value type accepts subclasses
--FILE--
<?php
class StdArrayClassValue
{
    public function __construct(public int $value)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }
}

class StdArrayClassValueChild extends StdArrayClassValue
{
}

function std_array_class_value_mixed(mixed $value): mixed
{
    return $value;
}

function main() {
    $array = std::array(StdArrayClassValue::class, 3);
    $array[0] = new StdArrayClassValue(1);
    var_dump($array[0]->getValue());

    $array[1] = std_array_class_value_mixed(new StdArrayClassValue(2));
    var_dump($array[1]->getValue());

    try {
        $array[2] = std_array_class_value_mixed(new StdArrayClassValueChild(3));
        var_dump($array[2]->getValue());
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
int(1)
int(2)
int(3)
