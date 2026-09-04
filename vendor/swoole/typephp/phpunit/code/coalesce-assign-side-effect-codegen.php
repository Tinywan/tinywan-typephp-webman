<?php

function sideEffectCall(): int
{
    echo "side effect!\n";
    return 41;
}

function coalesceCompoundRhs(): int
{
    $target = 1;
    $target ??= sideEffectCall() + 1;
    return $target;
}

function coalesceSimpleRhs(): int
{
    $target = 1;
    $target ??= sideEffectCall();
    return $target;
}
