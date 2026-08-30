<?php

#[Native]
class NativePropertyValue
{
    public int $value = 1;
}

function storeNativeInPhpProperty(): void
{
    $phpObject = new stdClass();
    $phpObject->value = new NativePropertyValue();
}
