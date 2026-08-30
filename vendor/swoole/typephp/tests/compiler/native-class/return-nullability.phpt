--TEST--
Native class: return boundaries preserve nullable and non-null pointer contracts
--FILE--
<?php

#[Native]
class NativeReturnBase
{
    public int $value = 42;
}

#[Native]
class NativeReturnChild extends NativeReturnBase
{
}

function maybeNativeReturn(bool $create): ?NativeReturnChild
{
    return $create ? new NativeReturnChild() : null;
}

function requireNativeReturn(bool $create): NativeReturnBase
{
    // The nullable producer is allowed as an expression, but the declared
    // non-null return boundary validates the resulting pointer exactly once.
    return maybeNativeReturn($create);
}

function suppressedNativeReturn(): NativeReturnBase
{
    return @maybeNativeReturn(true);
}

function missingNativeReturn(): NativeReturnBase
{
}

function main(): void
{
    var_dump(maybeNativeReturn(false) === null);
    var_dump(requireNativeReturn(true)->value);

    try {
        requireNativeReturn(false);
    } catch (Error $error) {
        echo "null return rejected\n";
    }

    $reporting = error_reporting();
    var_dump(suppressedNativeReturn()->value, error_reporting() === $reporting);

    try {
        missingNativeReturn();
    } catch (Error $error) {
        echo "missing return rejected\n";
    }
}
?>
--EXPECT--
bool(true)
int(42)
null return rejected
int(42)
bool(true)
missing return rejected
