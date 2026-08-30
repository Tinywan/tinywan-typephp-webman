--TEST--
Native class: chained assignment remains in the native object model
--FILE--
<?php

#[Native]
class NativeChainedValue
{
    public int $value = 42;
}

function main(): void
{
    $first = $second = new NativeChainedValue();
    var_dump($first->value, $second->value);
    var_dump($first === $second);
}
?>
--EXPECT--
int(42)
int(42)
bool(true)
