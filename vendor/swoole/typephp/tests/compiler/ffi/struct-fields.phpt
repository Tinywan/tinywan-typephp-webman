--TEST--
FFI struct field write and address
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
    $node = FFI::new(<<<'CDEF'
        struct node {
            int id;
            int value;
        }
    CDEF);
    $node->id = 123;
    $node->value = 456;
    $addr = FFI::addr($node);

    var_dump($node instanceof FFI\CData);
    var_dump($addr instanceof FFI\CData);
    var_dump(FFI::sizeof($node));
}
?>
--EXPECTF--
bool(true)
bool(true)
int(%d)
