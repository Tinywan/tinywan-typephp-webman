--TEST--
std map: 001
--FILE--
<?php
function main() {
    $map = std::map(Type::Int, Type::Int);
    $map[10] = 32;
    $map[10] += 10;
    $map[20] = 7;

    var_dump($map[10]);
    var_dump($map[20]);
    var_dump(count($map));
}
?>
--EXPECT--
int(42)
int(7)
int(2)
