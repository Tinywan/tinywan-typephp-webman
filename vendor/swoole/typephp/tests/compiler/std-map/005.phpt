--TEST--
std map: unsafe_cast
--FILE--
<?php
function std_map_unsafe_ptr_update($source): void
{
    $map = $source->toStdMap(Type::Int, Type::Int);
    var_dump($map[2]);
    $map[3] = 9;
}

function main() {
    $map = std::map(Type::Int, Type::Int);
    $map[1] = 1;
    $map[2] = 7;
    $map[3] = 3;

    std_map_unsafe_ptr_update($map);
    var_dump($map[3]);
}
?>
--EXPECT--
int(7)
int(9)
