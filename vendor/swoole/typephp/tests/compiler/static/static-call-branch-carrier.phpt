--TEST--
Native static calls initialize the class carrier outside conditional branches
--FILE--
<?php

class StaticBranchCarrier
{
    protected static array $cache = [];

    public static function normalize(string $value): string
    {
        if (isset(static::$cache[$value])) {
            return static::$cache[$value];
        }

        return static::$cache[$value] = strtoupper($value);
    }
}

function selectBranch(bool $first): string
{
    if ($first) {
        return StaticBranchCarrier::normalize('first');
    } else {
        return StaticBranchCarrier::normalize('second');
    }
}

function main(): void
{
    // The first static call in source order is deliberately not executed.
    var_dump(selectBranch(false));
    var_dump(selectBranch(true));
    var_dump(selectBranch(false));
}

?>
--EXPECT--
string(6) "SECOND"
string(5) "FIRST"
string(6) "SECOND"
