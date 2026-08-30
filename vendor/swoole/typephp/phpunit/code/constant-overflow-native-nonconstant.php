<?php

declare(strict_types=1);
use native_types;

function addToMaximum(int $value): int
{
    return PHP_INT_MAX + $value;
}

function main(): void
{
    var_dump(addToMaximum(0));
}
