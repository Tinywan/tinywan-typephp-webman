<?php

#[Native]
class NativeForwardA
{
    public ?NativeForwardB $next;
}

function nativeForwardIdentity(?NativeForwardB $value): ?NativeForwardB
{
    return $value;
}
