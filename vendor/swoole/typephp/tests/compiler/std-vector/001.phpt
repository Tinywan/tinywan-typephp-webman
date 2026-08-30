--TEST--
std vector: 001
--FILE--
<?php
function main() {
    $vector = std::vector(Type::Int);
    $vector[] = 1;
    $vector[] = 2;
    $vector[1] += 40;

    var_dump($vector[0]);
    var_dump($vector[1]);
    var_dump(count($vector));

    $floatVector = std::vector(Type::Float, 2);
    $floatVector[0] = 3.14;
    var_dump($floatVector[0] == 3.14);
    var_dump($floatVector[1] == 0.0);
}
?>
--EXPECT--
int(1)
int(42)
int(2)
bool(true)
bool(true)
