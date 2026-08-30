<?php

#[Native]
class NativeUntypedReturn {}

function makeNativeUntyped()
{
    return new NativeUntypedReturn();
}

function main(): void {}
