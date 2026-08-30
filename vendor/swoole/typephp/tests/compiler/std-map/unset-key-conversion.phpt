--TEST--
std containers: unset uses normal key conversion
--FILE--
<?php
function main() {
    $map = std::map(Type::String, Type::Int);
    $map[123] = 1;
    unset($map[123]);
    var_dump(count($map));

    $vector = std::vector(Type::Int, 1);
    $vector[0] = 42;
    unset($vector['0']);
    var_dump($vector[0]);
}
?>
--EXPECT--
int(0)
int(0)
