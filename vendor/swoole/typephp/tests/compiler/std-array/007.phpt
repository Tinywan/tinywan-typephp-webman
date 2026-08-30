--TEST--
std array: unsafe_cast
--FILE--
<?php
function std_array_unsafe_ptr_update($source): void
{
    $array = $source->toStdArray(Type::Int, 3);
    var_dump($array[1]);
    $array[2] = 9;
}

function main() {
    $array = std::array(Type::Int, 3);
    $array[0] = 1;
    $array[1] = 7;
    $array[2] = 3;

    std_array_unsafe_ptr_update($array);
    var_dump($array[2]);
}
?>
--EXPECT--
int(7)
int(9)
