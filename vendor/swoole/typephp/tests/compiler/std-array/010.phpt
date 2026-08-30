--TEST--
std array: nested same type copy
--FILE--
<?php
function main() {
    $a = std::array(Type::Int, 3);
    $b = std::array(std::array(Type::Int, 3), 2);

    $b[1][0] = 10;
    $b[1][1] = 20;
    $b[1][2] = 30;

    $a = $b[1];
    var_dump($a[0]);
    var_dump($a[1]);
    var_dump($a[2]);

    $a[0] = 99;
    var_dump($b[1][0]);

    $b[0] = $a;
    var_dump($b[0][0]);
    var_dump($b[0][1]);
    var_dump($b[0][2]);
}
?>
--EXPECT--
int(10)
int(20)
int(30)
int(10)
int(99)
int(20)
int(30)
