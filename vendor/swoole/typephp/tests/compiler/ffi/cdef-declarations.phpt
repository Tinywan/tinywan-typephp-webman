--TEST--
FFI cdef declarations create structs enums and functions
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
    $ffi = FFI::cdef(<<<'CDEF'
        typedef struct point {
            int x;
            int y;
        } point_t;

        enum color {
            COLOR_RED = 1,
            COLOR_GREEN = 2
        };

        int abs(int);
    CDEF
    , "libc.so.6");

    $point = FFI::new("struct point { int x; int y; }");
    $color = FFI::new("enum color { COLOR_RED = 1, COLOR_GREEN = 2 }");

    var_dump($point instanceof FFI\CData);
    var_dump($color instanceof FFI\CData);
    var_dump(FFI::sizeof($point));
    var_dump($ffi->abs(-7));
}
?>
--EXPECT--
bool(true)
bool(true)
int(8)
int(7)
