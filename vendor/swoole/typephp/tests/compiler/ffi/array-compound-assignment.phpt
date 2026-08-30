--TEST--
FFI array element compound assignment reads scalar value
--EXTENSIONS--
ffi
--ENV--
USE_ZEND_ALLOC=0
--INI--
ffi.enable=1
--FILE--
<?php

function main(): void
{
    $array = FFI::new("int[3]");
    $array[0] = 10;
    $array[1] = 20;
    $array[2] = 30;

    $result = ($array[0] += 5);
    var_dump($array[0]);
    var_dump($result);

    $array[1] *= 2;
    var_dump($array[1]);

    $array[2] -= $array[0];
    var_dump($array[2]);
}
?>
--EXPECT--
int(15)
int(15)
int(40)
int(15)
