<?php

#[Native]
class NativeGeneratorParameter
{
    public int $value = 1;
}

function nativeGeneratorParameter(NativeGeneratorParameter $value): iterable
{
    yield $value->value;
}

