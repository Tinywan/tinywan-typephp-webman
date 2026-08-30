<?php

use native_types;

function recursiveNativeInt(int $value): int
{
    return $value < 2
        ? 1
        : recursiveNativeInt($value - 2) + recursiveNativeInt($value - 1);
}
