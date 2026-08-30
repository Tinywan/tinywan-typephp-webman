--TEST--
std vector: same type copy
--FILE--
<?php
function main() {
    $a = std::vector(Type::Int);
    $b = std::vector(Type::Int);

    $b[] = 10;
    $b[] = 20;
    $a = $b;

    var_dump(count($a));
    var_dump($a[0]);
    var_dump($a[1]);

    $a[0] = 99;
    var_dump($b[0]);
}
?>
--EXPECT--
int(2)
int(10)
int(20)
int(10)
