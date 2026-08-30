<?php

#[Native]
class NativeArrayElement
{
    public int $value = 1;
}

function storeNativeInArray(): array
{
    return [new NativeArrayElement()];
}
