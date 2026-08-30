--TEST--
std vector: class value type rejects non-object value
--FILE--
<?php
class StdVectorRuntimeScalarValue
{
}

function std_vector_runtime_scalar_mixed(mixed $value): mixed
{
    return $value;
}

function main() {
    $vector = std::vector(StdVectorRuntimeScalarValue::class);

    try {
        $vector[] = std_vector_runtime_scalar_mixed(123);
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }

    try {
        $vector[] = std_vector_runtime_scalar_mixed(null);
    } catch (Throwable $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
The parameter `object` must be `object`, got `int`
The parameter `object` must be `object`, got `null`
