--TEST--
signed integer arithmetic keeps non-overflowing results as integers
--FILE--
<?php

function requireInt(int $value): void
{
    var_dump($value);
}

function main(): void
{
    $values = [-1, 1, 0];
    $negativeOne = $values[0];
    $positiveOne = $values[1];
    $zero = $values[2];

    requireInt($negativeOne + $positiveOne);
    requireInt($zero - $positiveOne);

    $negativeOne += $positiveOne;
    requireInt($negativeOne);

    $zero -= $positiveOne;
    requireInt($zero);
}
?>
--EXPECT--
int(0)
int(-1)
int(0)
int(-1)
