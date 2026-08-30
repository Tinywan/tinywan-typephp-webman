--TEST--
std array: 002
--FILE--
<?php
function main() {
    $array = std::array(std::array(std::array(Type::Int, 13), 16), 19);
    $index = 9;
    $index2 = 5;
    $array[$index2][$index][0] = 2026;
    $array[$index2][$index][12] = 2019;

    var_dump($array[$index2][$index][0]);
    var_dump(count($array[$index2][$index]));

    $value3 = $array[$index2][$index];
    var_dump($value3[12]);
    var_dump(count($value3));
}
?>
--EXPECT--
int(2026)
int(13)
int(2019)
int(13)

