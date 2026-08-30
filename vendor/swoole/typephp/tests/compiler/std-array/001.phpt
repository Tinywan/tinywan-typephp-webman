--TEST--
std array: 001
--FILE--
<?php
function main() {
    $array = std::array(Type::Int, 100);
    $array[99] = 2026;
    var_dump($array[99]);
    var_dump($array[10]);
    try {
        $index = 100;
        var_dump($array[$index]);
    } catch (throwable $e) {
        echo $e->getMessage();
    }
}
?>
--EXPECT--
int(2026)
int(0)
Array index out of bounds: index 100, size 100
