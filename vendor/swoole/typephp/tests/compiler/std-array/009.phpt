--TEST--
std array: assign to PHP array
--FILE--
<?php
function main() {
    $array = std::array(Type::Int, 3);
    $array[0] = 10;
    $array[1] = 20;
    $array[2] = 30;

    $copy = $array;
    var_dump(is_array($copy));
    var_dump(count($copy));
    var_dump($copy[0]);
    var_dump($copy[1]);
    var_dump($copy[2]);
}
?>
--EXPECT--
bool(true)
int(3)
int(10)
int(20)
int(30)
