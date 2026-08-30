--TEST--
std ordered_map: same type copy
--FILE--
<?php
function main() {
    $a = std::ordered_map(Type::Int, Type::Int);
    $b = std::ordered_map(Type::Int, Type::Int);

    $b[10] = 100;
    $b[20] = 200;
    $a = $b;

    var_dump(count($a));
    var_dump($a[10]);
    var_dump($a[20]);

    $a[10] = 999;
    var_dump($b[10]);
}
?>
--EXPECT--
int(2)
int(100)
int(200)
int(100)
