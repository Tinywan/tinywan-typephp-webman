--TEST--
std array: 003
--FILE--
<?php
function main() {
    $array = std::array(std::array(std::array(Type::Int, 13), 16), 19);
    $index = 9;
    $index2 = 5;
    $array[$index2][$index][0] = 2026;
    $array[$index2][$index][12] = 2019;

    $array2 = $array[$index2];
    var_dump($array2[$index][0]);
    var_dump($array2[$index][12]);
}
?>
--EXPECT--
int(2026)
int(2019)
