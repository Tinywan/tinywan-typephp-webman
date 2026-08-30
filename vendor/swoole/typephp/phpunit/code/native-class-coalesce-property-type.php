<?php

#[Native]
class NativeCoalescePropertyExpected {}

#[Native]
class NativeCoalescePropertyWrong {}

#[Native]
class NativeCoalesceHolder
{
    public ?NativeCoalescePropertyExpected $value;
}

function main(): void
{
    $holder = new NativeCoalesceHolder();
    $holder->value ??= new NativeCoalescePropertyWrong();
}
