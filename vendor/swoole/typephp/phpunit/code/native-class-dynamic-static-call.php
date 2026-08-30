<?php

#[Native]
class NativeDynamicStaticTarget
{
    public function method(): void {}
}

function main(): void
{
    $target = new NativeDynamicStaticTarget();
    $target::method();
}
