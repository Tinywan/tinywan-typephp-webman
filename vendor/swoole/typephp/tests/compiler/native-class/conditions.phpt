--TEST--
Native class: object pointers use PHP object truthiness in conditions
--FILE--
<?php

#[Native]
class NativeConditionValue {}

#[Native]
class NativeConditionWithConversion
{
    public function toBool(): bool
    {
        return false;
    }
}

function maybeNative(bool $present): ?NativeConditionValue
{
    return $present ? new NativeConditionValue() : null;
}

function main(): void
{
    $present = maybeNative(true);
    $missing = maybeNative(false);

    var_dump((bool) ($present && true));
    var_dump((bool) ($missing || false));
    var_dump(!$present, !$missing);

    if ($present) {
        echo "present\n";
    }

    $converted = new NativeConditionWithConversion();
    var_dump((bool) $converted);
    if ($converted) {
        echo "object truthiness\n";
    }
}
?>
--EXPECT--
bool(true)
bool(false)
bool(false)
bool(true)
present
bool(false)
object truthiness
