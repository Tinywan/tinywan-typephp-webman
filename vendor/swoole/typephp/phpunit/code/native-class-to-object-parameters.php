<?php

#[Native]
class InvalidNativeToObjectParameters
{
    public function toObject(string $class): object
    {
        return new stdClass();
    }
}

function main(): void
{
    $value = new InvalidNativeToObjectParameters();
    $value->toObject(stdClass::class);
}
