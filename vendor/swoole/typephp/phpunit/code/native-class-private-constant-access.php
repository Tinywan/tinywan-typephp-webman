<?php

#[Native]
class NativePrivateConstantOwner
{
    private const int VALUE = 1;
}

function main(): void
{
    var_dump(NativePrivateConstantOwner::VALUE);
}
