<?php

#[Native]
class NativeDynamicNewTarget {}

function main(): void
{
    $target = new NativeDynamicNewTarget();
    $value = new $target();
}
