<?php

declare(strict_types=1);

function main(): void
{
    $a = 1 << 64;
    $b = 1 >> 64;
    $c = -1 >> 64;
    $d = 1 >> -1;
    $e = 1 << 2;
    $f = 3 >> 1;
}
