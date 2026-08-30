<?php

#[Native]
class NativeExplicitConstructorCall
{
    public function __construct()
    {
    }
}

function main(): void
{
    $value = new NativeExplicitConstructorCall();
    $value->__construct();
}
