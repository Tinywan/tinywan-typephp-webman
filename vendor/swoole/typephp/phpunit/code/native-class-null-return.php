<?php

#[Native]
class NativeNullReturnValue
{
}

function invalidNativeNullReturn(): NativeNullReturnValue
{
    return null;
}
