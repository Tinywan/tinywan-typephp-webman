--TEST--
Native class: nullable parameters and returns stay native pointers
--FILE--
<?php

#[Native]
class NativeNullableValue
{
    public int $value = 42;
}

function maybeNative(bool $create): ?NativeNullableValue
{
    if ($create) {
        return new NativeNullableValue();
    }
    return null;
}

function readMaybeNative(?NativeNullableValue $value): int
{
    if ($value === null) {
        return -1;
    }
    return $value->value;
}

function main(): void
{
    var_dump(readMaybeNative(maybeNative(false)));
    var_dump(readMaybeNative(maybeNative(true)));
}
?>
--EXPECT--
int(-1)
int(42)
