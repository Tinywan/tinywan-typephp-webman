<?php

use varint_types;

function recursivePhpInt(int $value): int
{
    return $value < 2
        ? 1
        : recursivePhpInt($value - 2) + recursivePhpInt($value - 1);
}
