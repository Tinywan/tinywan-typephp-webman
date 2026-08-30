--TEST--
std vector: count uses current runtime size
--FILE--
<?php
function main() {
    $vector = std::vector(Type::Int, 2);
    $vector[] = 3;
    var_dump(count($vector));

    $arrays = std::vector(Type::Array);
    $arrays[] = [1, 2, 3, 4];
    var_dump(count($arrays[0]));
}
?>
--EXPECT--
int(3)
int(4)
