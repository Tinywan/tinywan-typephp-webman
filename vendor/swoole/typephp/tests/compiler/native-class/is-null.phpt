--TEST--
Native class: is_null checks the nullable pointer without ZendVM
--FILE--
<?php

#[Native]
class NativeNullableCheck {}

function maybeNativeNullableCheck(bool $create): ?NativeNullableCheck
{
    return $create ? new NativeNullableCheck() : null;
}

function main(): void
{
    var_dump(is_null(maybeNativeNullableCheck(false)));
    var_dump(is_null(maybeNativeNullableCheck(true)));
}
?>
--EXPECT--
bool(true)
bool(false)
