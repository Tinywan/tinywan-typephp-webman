--TEST--
Native class: error suppression preserves typed pointer identity
--FILE--
<?php

#[Native]
class NativeSuppressedExpression
{
    public int $value = 1;
}

function main(): void
{
    $first = @new NativeSuppressedExpression();
    $second = $first;
    $second->value = 42;
    var_dump($first === $second, $first->value);
}
?>
--EXPECT--
bool(true)
int(42)
