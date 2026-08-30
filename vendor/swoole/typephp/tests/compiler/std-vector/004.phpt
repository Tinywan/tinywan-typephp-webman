--TEST--
std vector: class value type accepts typed parameter subclass at runtime
--FILE--
<?php
class StdVectorRuntimeClassValue
{
    public function __construct(public int $value)
    {
    }
}

class StdVectorRuntimeClassValueChild extends StdVectorRuntimeClassValue
{
}

function std_vector_runtime_class_value(StdVectorRuntimeClassValue $value): void
{
    $vector = std::vector(StdVectorRuntimeClassValue::class);

    try {
        $vector[] = $value;
        var_dump($vector[0]->value);
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}

function main() {
    std_vector_runtime_class_value(new StdVectorRuntimeClassValueChild(1));
}
?>
--EXPECT--
int(1)
