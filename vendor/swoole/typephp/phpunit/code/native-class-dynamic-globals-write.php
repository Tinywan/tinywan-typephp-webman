<?php

#[Native]
class NativeDynamicGlobalValue
{
    public int $value = 1;
}

function writeNativeDynamicGlobal(string $name): void
{
    $GLOBALS[$name] = new NativeDynamicGlobalValue();
}
