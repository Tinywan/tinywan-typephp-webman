<?php

#[Native]
class NativeGeneratorFactoryValue
{
    public int $value = 1;
}

function createNativeGeneratorValue(): NativeGeneratorFactoryValue
{
    return new NativeGeneratorFactoryValue();
}

function nativeGeneratorFactory(): iterable
{
    $value = createNativeGeneratorValue();
    yield $value->value;
}

