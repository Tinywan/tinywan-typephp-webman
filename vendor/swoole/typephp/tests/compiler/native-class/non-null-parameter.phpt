--TEST--
Native class: non-null parameters are validated at function entry
--FILE--
<?php

#[Native]
class NativeRequiredArgument
{
    public int $value = 42;
}

function acceptRequiredNative(NativeRequiredArgument $value): void
{
    echo "entered\n";
}

function readAfterPossibleRebind(NativeRequiredArgument $value, bool $clear): int
{
    $before = $value->value;
    if ($clear) {
        $value = null;
    }
    // The assignment above conservatively invalidates the entry proof, so
    // this access must check the pointer even on a control-flow merge.
    return $before + $value->value;
}

function main(): void
{
    $value = new NativeRequiredArgument();
    var_dump(readAfterPossibleRebind($value, false));
    try {
        readAfterPossibleRebind($value, true);
    } catch (Error $error) {
        echo "rebound rejected\n";
    }
    unset($value);
    try {
        acceptRequiredNative($value);
    } catch (Error $error) {
        echo "rejected\n";
    }
}
?>
--EXPECT--
int(84)
rebound rejected
rejected
