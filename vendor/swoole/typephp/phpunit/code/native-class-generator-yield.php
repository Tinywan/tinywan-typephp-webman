<?php

#[Native]
class NativeGeneratorYieldValue
{
}

function createNativeGeneratorYieldValue(): NativeGeneratorYieldValue
{
    return new NativeGeneratorYieldValue();
}

function nativeGeneratorYield(): iterable
{
    yield createNativeGeneratorYieldValue();
}

