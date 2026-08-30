<?php

#[Native]
class NativeObjectCastValue {}

function castNativeToObject(NativeObjectCastValue $value): object
{
    return (object) $value;
}

