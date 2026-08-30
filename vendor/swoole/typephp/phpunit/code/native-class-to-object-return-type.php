<?php

#[Native]
class InvalidNativeToObjectReturn
{
    public function toObject(): array
    {
        return [];
    }
}

function main(): void
{
    $value = new InvalidNativeToObjectReturn();
    $value->toObject();
}
