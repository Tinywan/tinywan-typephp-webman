<?php

#[Native]
class NativeGeneratorLocal
{
    public int $value = 1;
}

function nativeGeneratorLocal(): iterable
{
    $value = new NativeGeneratorLocal();
    yield $value->value;
}

