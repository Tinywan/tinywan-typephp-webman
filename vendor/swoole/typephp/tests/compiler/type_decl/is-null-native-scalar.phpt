--TEST--
is_null returns false for fixed native scalars and preserves operand side effects
--FILE--
<?php

use native_types;

function checkInt(int $value): bool
{
    return is_null($value);
}

function checkNullableInt(?int $value): bool
{
    return is_null($value);
}

function emitInt(): int
{
    echo "emitInt\n";
    return 42;
}

function main(): void
{
    $intValue = 42;
    $floatValue = 1.5;
    $boolValue = true;

    var_dump(is_null($intValue));
    var_dump(is_null($floatValue));
    var_dump(is_null($boolValue));
    var_dump(checkInt($intValue));
    var_dump(is_null(emitInt()));
    var_dump(checkNullableInt(null));
    var_dump(checkNullableInt($intValue));
}
?>
--EXPECT--
bool(false)
bool(false)
bool(false)
bool(false)
emitInt
bool(false)
bool(true)
bool(false)
