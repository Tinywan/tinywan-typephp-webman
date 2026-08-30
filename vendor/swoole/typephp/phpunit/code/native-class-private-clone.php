<?php

#[Native]
class NativePrivateClone
{
    private function __clone(): void {}
}

function main(): void
{
    $value = new NativePrivateClone();
    $copy = clone $value;
}
