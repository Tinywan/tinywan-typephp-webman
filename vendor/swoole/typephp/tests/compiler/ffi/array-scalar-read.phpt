--TEST--
FFI array element scalar read matches PHP
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

    var_dump($array[0]);
    var_dump($array[0] + $array[1]);
    var_dump($array[0] === 10);
    var_dump($array[0] == 10);
    var_dump(is_int($array[0]));
}
?>
--EXPECT--
int(10)
int(30)
bool(true)
bool(true)
bool(true)
