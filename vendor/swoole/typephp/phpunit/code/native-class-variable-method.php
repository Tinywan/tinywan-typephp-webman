<?php

#[Native]
class NativeVariableMethod
{
    public function method(): void {}
}

function main(): void
{
    $value = new NativeVariableMethod();
    $method = 'method';
    $value->$method();
}
