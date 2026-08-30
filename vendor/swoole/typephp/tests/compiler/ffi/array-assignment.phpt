--TEST--
FFI multidimensional array assignment keeps CData containers
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
    $ffi = FFI::cdef();

    $matrix = $ffi->new("int[2][2]");
    $row = $ffi->new("int[2]");
    $row[0] = 11;
    $row[1] = 22;
    $matrix[1] = $row;

    var_dump($matrix instanceof FFI\CData);
    var_dump($row instanceof FFI\CData);
    var_dump(FFI::sizeof($matrix));
    var_dump(FFI::sizeof($row));
}
?>
--EXPECT--
bool(true)
bool(true)
int(16)
int(8)
