<?php

#[Native]
class NativeCoalesceExpected {}

#[Native]
class NativeCoalesceWrong {}

function maybeExpected(): ?NativeCoalesceExpected
{
    return null;
}

function main(): void
{
    $value = maybeExpected();
    $value ??= new NativeCoalesceWrong();
}
