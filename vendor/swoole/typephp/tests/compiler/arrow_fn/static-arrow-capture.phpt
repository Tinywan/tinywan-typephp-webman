--TEST--
static arrow function captures local variables by value
--FILE--
<?php

function main(): void
{
    $factor = 3;
    $map = static fn (int $value): int => $value * $factor;
    $factor = 10;

    var_dump($map(4));
}
?>
--EXPECT--
int(12)
