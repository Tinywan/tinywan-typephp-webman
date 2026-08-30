--TEST--
std vector: assign to PHP array
--FILE--
<?php
function main() {
    $vector = std::vector(Type::Int);
    $vector[] = 10;
    $vector[] = 20;

    $copy = $vector;
    var_dump(is_array($copy));
    var_dump(count($copy));
    var_dump($copy[0]);
    var_dump($copy[1]);
}
?>
--EXPECT--
bool(true)
int(2)
int(10)
int(20)
