<?php

#[Native]
class NativeReferenceMethodValue {}

function invalidNativeReferenceMethod(): void
{
    $value = new NativeReferenceMethodValue();
    $value->toRef();
}
