<?php

#[Native]
class NativeToObject
{
    public function toObject(): object
    {
        return new stdClass();
    }
}

function main(): void
{
    $value = new NativeToObject();
    $value->toObject();
}
