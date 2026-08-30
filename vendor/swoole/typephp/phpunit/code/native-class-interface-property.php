<?php

interface NativeInterfacePropertyContract
{
    public function value(): int;
}

#[Native]
class NativeInterfacePropertyValue implements NativeInterfacePropertyContract
{
    public function value(): int
    {
        return 1;
    }
}

#[Native]
class NativeInterfacePropertyHolder
{
    public NativeInterfacePropertyContract $value;
}

function main(): void
{
    $holder = new NativeInterfacePropertyHolder();
    $holder->value = new NativeInterfacePropertyValue();
}
