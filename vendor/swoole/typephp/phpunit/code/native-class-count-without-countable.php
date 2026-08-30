<?php

#[Native]
class NativeCounterWithoutContract
{
    public function count(): int
    {
        return 1;
    }
}

function main(): void
{
    $value = new NativeCounterWithoutContract();
    count($value);
}
