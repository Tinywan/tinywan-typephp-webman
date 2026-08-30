--TEST--
FFI addr cast typeof and sizeof
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
    $value = FFI::new("int");
    $value->cdata = 123;

    $addr = FFI::addr($value);
    $ptr = FFI::cast("int*", $addr);
    $type = FFI::typeof($value);

    var_dump($addr instanceof FFI\CData);
    var_dump($ptr instanceof FFI\CData);
    var_dump($type instanceof FFI\CType);
    var_dump(FFI::sizeof($type));
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
int(4)
