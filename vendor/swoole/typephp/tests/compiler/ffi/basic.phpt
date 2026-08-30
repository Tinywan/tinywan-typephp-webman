--TEST--
FFI basic CData and libc call
--EXTENSIONS--
ffi
--ENV--
USE_ZEND_ALLOC=0
--INI--
ffi.enable=1
--SKIPIF--
<?php
try {
    FFI::cdef("int abs(int);", "libc.so.6");
} catch (Throwable $e) {
    die("skip FFI::cdef is not available: " . $e->getMessage());
}
?>
--FILE--
<?php

function main(): void
{
    $array = FFI::new("int[3]");
    $array[0] = 10;
    $array[1] = 20;
    $array[2] = 30;
    var_dump($array instanceof FFI\CData);

    $libc = FFI::cdef("int abs(int);", "libc.so.6");
    var_dump($libc->abs(-42));
}
?>
--EXPECT--
bool(true)
int(42)
