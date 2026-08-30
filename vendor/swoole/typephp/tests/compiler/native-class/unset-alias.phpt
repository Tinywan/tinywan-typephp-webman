--TEST--
Native class: unset and null clear only the local pointer slot
--FILE--
<?php

#[Native]
class NativeAliasValue
{
    public int $value = 42;
}

function main(): void
{
    $first = new NativeAliasValue();
    $alias = $first;
    unset($first);
    var_dump($first === null, $alias->value);

    $second = $alias;
    $alias = null;
    var_dump($alias === null, $second->value);
}
?>
--EXPECT--
bool(true)
int(42)
bool(true)
int(42)
