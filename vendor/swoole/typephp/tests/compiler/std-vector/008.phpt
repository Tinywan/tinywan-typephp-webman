--TEST--
std vector: unsafe_cast class value type mismatch
--FILE--
<?php
class StdVectorUnsafeCastBase
{
}

class StdVectorUnsafeCastOther
{
}

function std_vector_unsafe_ptr_class_type_mismatch($source): void
{
    $vector = $source->toStdVector(StdVectorUnsafeCastOther::class);
}

function main() {
    $vector = std::vector(StdVectorUnsafeCastBase::class);
    try {
        std_vector_unsafe_ptr_class_type_mismatch($vector);
    } catch (TypeError $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
std container type mismatch
