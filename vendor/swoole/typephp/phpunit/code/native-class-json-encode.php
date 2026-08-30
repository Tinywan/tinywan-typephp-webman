<?php

#[Native]
class NativeJsonValue
{
    public function toArray(): array
    {
        return [];
    }
}

function main(): void
{
    $value = new NativeJsonValue();
    json_encode($value);
}
