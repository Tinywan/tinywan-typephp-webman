<?php

#[Native]
class NativeUntypedParameterValue {}

function acceptsUntypedNativeValue($value): void {}

function invalidNativeUntypedParameter(): void
{
    acceptsUntypedNativeValue(new NativeUntypedParameterValue());
}
