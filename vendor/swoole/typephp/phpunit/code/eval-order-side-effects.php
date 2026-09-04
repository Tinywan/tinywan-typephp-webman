<?php

function pair(int $a, int $b): string
{
    return $a . ',' . $b;
}

function callArgOrder(): string
{
    $j = 1;
    return pair($j, $j = 5);
}

function concatOrder(): string
{
    $m = 1;
    return $m . ',' . ($m = 9);
}

function plainArithmeticUnchanged(): int
{
    $k = 1;
    return $k + ($k = 5);
}

function pairValue(mixed $a, mixed $b): string
{
    return $a . ',' . $b;
}

function castWrappedCallArgOrder(): string
{
    $i = 1;
    return pair($i, (int) ($i = 5));
}

function notWrappedCallArgOrder(): string
{
    $k = 1;
    return pairValue($k, !($k = 0));
}
