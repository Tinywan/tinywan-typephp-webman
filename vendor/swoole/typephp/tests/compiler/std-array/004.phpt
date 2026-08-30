--TEST--
std array: 004
--FILE--
<?php
function main() {
    $array = std::array(Type::Float, 100);
    $array[99] = 2026.22;
    $array[33] = 3.1415;
    var_dump($array[99] == 2026.22);
    var_dump($array[33] == 3.1415);
    var_dump($array[11] == 0);

    $array2 = std::array(Type::Bool, 100);
    $array2[99] = 2026.22;
    $array2[33] = "";
    var_dump($array2[99] === true);
    var_dump($array2[33] === false);
    var_dump($array2[11] === false);

    var_dump($array2[99] == true);
    var_dump($array2[33] == false);
    var_dump($array2[11] == false);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)
bool(true)