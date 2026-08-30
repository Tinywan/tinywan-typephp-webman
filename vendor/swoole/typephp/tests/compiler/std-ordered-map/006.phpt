--TEST--
std ordered_map: unsafe_cast type mismatch
--FILE--
<?php
function std_map_unsafe_ptr_type_mismatch($source): void
{
    $map = $source->toStdMap(Type::String, Type::Float);
}

function main() {
    $map = std::ordered_map(Type::String, Type::Int);
    try {
        std_map_unsafe_ptr_type_mismatch($map);
    } catch (TypeError $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
std container type mismatch
