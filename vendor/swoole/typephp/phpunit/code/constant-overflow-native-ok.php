<?php

declare(strict_types=1);
use native_types;

function main(): void
{
    var_dump(PHP_INT_MAX + 0);
    var_dump(PHP_INT_MIN - 0);
    var_dump(4 / 2);
    var_dump((1 + 1) / 4);
    var_dump(5 % 2);
}
