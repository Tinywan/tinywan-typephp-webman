<?php

#[Native]
class NativeDynamicConstantTarget
{
    public const VALUE = 1;
}

function main(): void
{
    $target = new NativeDynamicConstantTarget();
    var_dump($target::VALUE);
}
