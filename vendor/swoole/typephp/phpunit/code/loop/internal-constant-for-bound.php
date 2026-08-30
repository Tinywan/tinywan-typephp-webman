<?php

function loopInternalConstantForBound(): bool
{
    $sum = 0;
    for ($i = 0; $i < PHP_FD_SETSIZE; $i++) {
        $sum += $i & 1;
    }
    return $sum > 0;
}
