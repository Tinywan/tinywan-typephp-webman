<?php

#[Native]
class NativeArrayAccessOperand {}

function main(): void
{
    $value = new NativeArrayAccessOperand();
    var_dump($value[0]);
}
