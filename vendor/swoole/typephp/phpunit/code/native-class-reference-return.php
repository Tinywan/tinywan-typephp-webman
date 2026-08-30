<?php

#[Native]
class NativeReferenceReturnValue {}

function &invalidNativeReferenceReturn(): NativeReferenceReturnValue
{
    $value = new NativeReferenceReturnValue();
    return $value;
}
