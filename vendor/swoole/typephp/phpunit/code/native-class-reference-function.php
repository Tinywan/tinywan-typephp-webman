<?php

#[Native]
class NativeReferenceFunctionValue {}

function acceptsReference(&$value): void {}

function invalidNativeReferenceFunction(): void
{
    $value = new NativeReferenceFunctionValue();
    acceptsReference(refval($value));
}
