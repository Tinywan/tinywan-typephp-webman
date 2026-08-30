<?php

#[Native]
class NativeArrayAccessWrite {}

function main(): void
{
    $value = new NativeArrayAccessWrite();
    $value[0] = 1;
}
