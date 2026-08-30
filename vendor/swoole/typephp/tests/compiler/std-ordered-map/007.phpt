--TEST--
std ordered_map: assign to PHP array
--FILE--
<?php
function main() {
    $map = std::ordered_map(Type::Int, Type::Int);
    $map[10] = 100;
    $map[20] = 200;

    $copy = $map;
    var_dump(is_array($copy));
    var_dump(count($copy));
    var_dump($copy[10]);
    var_dump($copy[20]);
}
?>
--EXPECT--
bool(true)
int(2)
int(100)
int(200)
