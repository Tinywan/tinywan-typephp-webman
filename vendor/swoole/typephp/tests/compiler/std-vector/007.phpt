--TEST--
std vector: unsafe_cast type mismatch
--FILE--
<?php
function std_vector_unsafe_ptr_type_mismatch($source): void
{
    $vector = $source->toStdVector(Type::Float);
}

function main() {
    $vector = std::vector(Type::Int, 3);
    try {
        std_vector_unsafe_ptr_type_mismatch($vector);
    } catch (TypeError $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
std container type mismatch
