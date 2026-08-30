--TEST--
std vector: unsafe_cast
--FILE--
<?php
function std_vector_unsafe_ptr_update($source): void
{
    $vector = $source->toStdVector(Type::Int);
    var_dump($vector[1]);
    $vector[2] = 9;
}

function main() {
    $vector = std::vector(Type::Int, 3);
    $vector[0] = 1;
    $vector[1] = 7;
    $vector[2] = 3;

    std_vector_unsafe_ptr_update($vector);
    var_dump($vector[2]);
}
?>
--EXPECT--
int(7)
int(9)
