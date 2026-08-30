<?php

interface NativeInterfaceArgumentContract
{
    public function value(): int;
}

#[Native]
class NativeInterfaceArgumentValue implements NativeInterfaceArgumentContract
{
    public function value(): int
    {
        return 1;
    }
}

function consumeNativeInterfaceArgument(NativeInterfaceArgumentContract $value): int
{
    return $value->value();
}

function main(): void
{
    consumeNativeInterfaceArgument(new NativeInterfaceArgumentValue());
}
