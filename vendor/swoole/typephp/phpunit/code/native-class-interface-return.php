<?php

interface NativeInterfaceReturnContract
{
    public function value(): int;
}

#[Native]
class NativeInterfaceReturnValue implements NativeInterfaceReturnContract
{
    public function value(): int
    {
        return 1;
    }
}

function makeNativeInterfaceReturn(): NativeInterfaceReturnContract
{
    return new NativeInterfaceReturnValue();
}
